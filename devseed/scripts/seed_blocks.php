<?php
/**
 * Populate a course page's block drawer with every block legitimately addable
 * to a course view page, using Moodle's own applicability rules and block manager.
 *
 * Usage: php /tmp/seed_blocks.php <courseid> [--apply]
 */

define('CLI_SCRIPT', true);
require('/var/www/html/public/config.php');
require_once($CFG->dirroot . '/lib/blocklib.php');

global $DB, $CFG, $PAGE;

// Capability checks need a real user.
\core\cron::setup_user(get_admin());

$courseid = (int) ($argv[1] ?? 15);
$apply = in_array('--apply', $argv, true);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);
$pagetype = 'course-view-' . $course->format;

echo "Course: {$course->fullname} (id {$courseid}), context {$context->id}, pagetype {$pagetype}\n";
echo $apply ? "MODE: APPLY\n" : "MODE: DRY RUN (pass --apply to write)\n";
echo str_repeat('=', 76) . "\n";

$existing = array_values($DB->get_records_menu('block_instances',
    ['parentcontextid' => $context->id], '', 'id, blockname'));
echo 'Already present (' . count($existing) . '): '
    . ($existing ? implode(', ', array_unique($existing)) : '(none)') . "\n\n";

$blocks = $DB->get_records('block', null, 'name ASC');
$toadd = [];
$skipped = [];

foreach ($blocks as $block) {
    $name = $block->name;

    if (!$block->visible) {
        $skipped[$name] = 'plugin disabled site-wide';
        continue;
    }
    if (!file_exists("{$CFG->dirroot}/blocks/{$name}/block_{$name}.php")) {
        $skipped[$name] = 'plugin files missing';
        continue;
    }

    try {
        $blockobj = block_instance($name);
    } catch (Throwable $e) {
        $skipped[$name] = 'instantiation failed: ' . $e->getMessage();
        continue;
    }
    if (!$blockobj) {
        $skipped[$name] = 'could not instantiate';
        continue;
    }

    // Canonical Moodle check: does this block apply to this page type?
    // (blocks_name_allowed_in_format instantiates internally, so it is safe;
    // read the formats off our own instance for the message.)
    if (!blocks_name_allowed_in_format($name, $pagetype)) {
        $skipped[$name] = 'not applicable to ' . $pagetype . ' '
            . json_encode($blockobj->applicable_formats());
        continue;
    }

    if (in_array($name, $existing, true) && !$blockobj->instance_allow_multiple()) {
        $skipped[$name] = 'already present (multiples not allowed)';
        continue;
    }

    $toadd[$name] = $blockobj->get_title() ?: $name;
}

echo 'WILL ADD (' . count($toadd) . "):\n";
foreach ($toadd as $name => $title) {
    printf("  + %-26s %s\n", $name, $title);
}
echo "\nSKIPPED (" . count($skipped) . "):\n";
foreach ($skipped as $name => $why) {
    printf("  - %-26s %s\n", $name, substr($why, 0, 66));
}

if (!$apply) {
    echo "\nDry run complete. Re-run with --apply to write.\n";
    exit(0);
}

// --- Add via the real block manager ---------------------------------------
$page = new moodle_page();
$page->set_context($context);
$page->set_course($course);
$page->set_pagelayout('course');
$page->set_pagetype($pagetype);
$page->blocks->add_region(BLOCK_POS_RIGHT, false);
$page->blocks->load_blocks();

echo "\nAdding blocks to the " . BLOCK_POS_RIGHT . " (drawer) region...\n";
$added = 0;
$weight = 0;
foreach ($toadd as $name => $title) {
    try {
        $page->blocks->add_block($name, BLOCK_POS_RIGHT, $weight++, false, 'course-view-*');
        $added++;
    } catch (Throwable $e) {
        echo "  FAILED {$name}: " . $e->getMessage() . "\n";
    }
}

purge_all_caches();

$final = $DB->count_records('block_instances', ['parentcontextid' => $context->id]);
echo "\nAdded {$added} block(s). Course context now has {$final} block instance(s).\n";
echo "URL: {$CFG->wwwroot}/course/view.php?id={$courseid}\n";
