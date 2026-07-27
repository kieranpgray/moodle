<?php
/**
 * Dev seeder for surface/depth review (MDL-87805).
 *
 *  Part 1 - adds subsection variations to Biology 101 (course 15).
 *  Part 2 - builds a "Surface & depth showcase" course exercising every
 *           section/activity state that renders with a distinct surface.
 *
 * Run: php /tmp/seed_course_content.php
 */

define('CLI_SCRIPT', true);
require('/var/www/html/public/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');

global $DB, $CFG;

$BIO_COURSE = 15;
$SHOWCASE_SHORTNAME = 'DEPTH-DEMO';

$log = [];
$problems = [];

function step(string $what, callable $fn) {
    global $log, $problems;
    try {
        $r = $fn();
        $log[] = "  OK   {$what}";
        return $r;
    } catch (Throwable $e) {
        $problems[] = "{$what}: " . $e->getMessage();
        $log[] = "  FAIL {$what} -- " . $e->getMessage();
        return null;
    }
}

// Stealth activities need this site-wide.
set_config('allowstealth', 1);
mtrace('Set allowstealth = 1');

$dg = \core\test\phpunit\phpunit_util::get_data_generator();

/** Create a subsection in $sectionnum, return [cm, delegated section record]. */
function make_subsection($dg, int $courseid, int $sectionnum, string $name): array {
    global $DB;
    $inst = $dg->create_module('subsection', [
        'course' => $courseid,
        'section' => $sectionnum,
        'name' => $name,
    ]);
    $delegated = $DB->get_record('course_sections', [
        'course' => $courseid,
        'component' => 'mod_subsection',
        'itemid' => $inst->id,
    ]);
    if (!$delegated) {
        throw new moodle_exception('Delegated section not created for subsection "' . $name . '"');
    }
    return [$inst, $delegated];
}

/** Availability JSON for a date restriction. $shown=false means fully hidden. */
function date_restriction(int $time, bool $shown = true, string $dir = '>='): string {
    return json_encode([
        'op' => '&',
        'c' => [['type' => 'date', 'd' => $dir, 't' => $time]],
        'showc' => [$shown],
    ]);
}

$future = time() + (30 * DAYSECS);

// ---------------------------------------------------------------------------
// PART 1 - Biology 101 subsection variations
// ---------------------------------------------------------------------------
mtrace("\n=== Part 1: Biology 101 (course {$BIO_COURSE}) subsections ===");

$biocourse = $DB->get_record('course', ['id' => $BIO_COURSE], '*', MUST_EXIST);

// 1a. Populated subsection with a mix of activity types.
step('Biology: populated subsection "Respiration pathways"', function () use ($dg, $BIO_COURSE) {
    [$inst, $sec] = make_subsection($dg, $BIO_COURSE, 6, 'Respiration pathways (nested)');
    $n = $sec->section;
    $dg->create_module('page', ['course' => $BIO_COURSE, 'section' => $n,
        'name' => 'Glycolysis explained', 'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1]);
    $dg->create_module('page', ['course' => $BIO_COURSE, 'section' => $n,
        'name' => 'The Krebs cycle', 'showdescription' => 1,
        'intro' => 'Description shown on the course page, to show the altcontent surface.']);
    $dg->create_module('forum', ['course' => $BIO_COURSE, 'section' => $n,
        'name' => 'Respiration Q&A']);
    $dg->create_module('label', ['course' => $BIO_COURSE, 'section' => $n,
        'intro' => '<p>Inline label inside a subsection — renders without its own card in non-editing view.</p>']);
    return true;
});

// 1b. Deliberately empty subsection.
step('Biology: empty subsection', function () use ($dg, $BIO_COURSE) {
    make_subsection($dg, $BIO_COURSE, 6, 'Empty subsection (no activities)');
    return true;
});

// 1c. Hidden subsection - delegated section set invisible.
step('Biology: hidden subsection', function () use ($dg, $BIO_COURSE) {
    global $DB;
    [$inst, $sec] = make_subsection($dg, $BIO_COURSE, 7, 'Hidden subsection (teacher only)');
    $dg->create_module('page', ['course' => $BIO_COURSE, 'section' => $sec->section,
        'name' => 'Draft notes (inside hidden subsection)']);
    set_section_visible($BIO_COURSE, $sec->section, 0);
    return true;
});

// 1d. Subsection containing a date-restricted activity.
step('Biology: subsection with restricted activity', function () use ($dg, $BIO_COURSE, $future) {
    global $DB;
    [$inst, $sec] = make_subsection($dg, $BIO_COURSE, 7, 'Restricted contents (nested)');
    $open = $dg->create_module('page', ['course' => $BIO_COURSE, 'section' => $sec->section,
        'name' => 'Unlocks in 30 days (greyed out with info)']);
    $DB->set_field('course_modules', 'availability', date_restriction($future, true),
        ['id' => $open->cmid]);
    $shut = $dg->create_module('page', ['course' => $BIO_COURSE, 'section' => $sec->section,
        'name' => 'Completely hidden until unlocked']);
    $DB->set_field('course_modules', 'availability', date_restriction($future, false),
        ['id' => $shut->cmid]);
    return true;
});

rebuild_course_cache($BIO_COURSE, true);
mtrace('Rebuilt Biology 101 course cache.');

// ---------------------------------------------------------------------------
// PART 2 - Surface & depth showcase course
// ---------------------------------------------------------------------------
mtrace("\n=== Part 2: Surface & depth showcase course ===");

$existing = $DB->get_record('course', ['shortname' => $SHOWCASE_SHORTNAME]);
if ($existing) {
    mtrace("Showcase course already exists (id {$existing->id}) - deleting and rebuilding.");
    delete_course($existing->id, false);
}

$showcase = $dg->create_course([
    'fullname' => 'Surface & depth showcase',
    'shortname' => $SHOWCASE_SHORTNAME,
    'category' => $biocourse->category,
    'format' => 'topics',
    'numsections' => 8,
    'enablecompletion' => 1,
    'summary' => 'Demonstrates every section/activity state that renders with a distinct '
        . 'surface treatment under the new depth model (MDL-87805).',
    'summaryformat' => FORMAT_HTML,
]);
$SC = $showcase->id;
mtrace("Created showcase course id {$SC}");

// Force single-page display: the recessed grey activity well only renders in this mode.
$DB->execute("DELETE FROM {course_format_options} WHERE courseid = ? AND name = 'coursedisplay'", [$SC]);
$DB->insert_record('course_format_options', (object) [
    'courseid' => $SC, 'format' => 'topics', 'sectionid' => 0,
    'name' => 'coursedisplay', 'value' => 0,
]);

// Name the sections.
$sectionnames = [
    1 => '1. Standard section — activity + badge variations',
    2 => '2. Highlighted (current) section',
    3 => '3. Hidden section — teacher only',
    4 => '4. Date-restricted section',
    5 => '5. Subsections — populated, empty, hidden',
    6 => '6. Empty section (grey well, no activities)',
    7 => '7. Activity visibility matrix',
    8 => '8. Extra section',
];
foreach ($sectionnames as $num => $name) {
    $DB->set_field('course_sections', 'name', $name, ['course' => $SC, 'section' => $num]);
}

// --- Section 1: activity + badge variations -------------------------------
step('Showcase S1: file resource with type badge (badge-none)', function () use ($dg, $SC) {
    global $DB;
    $res = $dg->create_module('resource', ['course' => $SC, 'section' => 1,
        'name' => 'Lab safety handout (file — shows type badge)']);
    // "Show type" is what renders the pale bordered badge-none pill.
    $DB->set_field('resource', 'displayoptions',
        serialize(['printintro' => 1, 'showsize' => 1, 'showtype' => 1]),
        ['id' => $res->id]);
    return true;
});

step('Showcase S1: forum with unread post (coloured badge)', function () use ($dg, $SC) {
    $forum = $dg->create_module('forum', ['course' => $SC, 'section' => 1,
        'name' => 'Class discussion (unread badge)', 'trackingtype' => 2]);
    $fg = $dg->get_plugin_generator('mod_forum');
    $fg->create_discussion([
        'course' => $SC,
        'forum' => $forum->id,
        'userid' => 6,
        'name' => 'Welcome to the discussion',
        'message' => 'First post — creates an unread badge for everyone else.',
    ]);
    return true;
});

step('Showcase S1: page with description shown on course page', function () use ($dg, $SC) {
    $dg->create_module('page', ['course' => $SC, 'section' => 1,
        'name' => 'Reading: cell membranes', 'showdescription' => 1,
        'intro' => 'This description renders inside the activity card as .activity-description.']);
    return true;
});

step('Showcase S1: inline label', function () use ($dg, $SC) {
    $dg->create_module('label', ['course' => $SC, 'section' => 1,
        'intro' => '<p><strong>Inline label.</strong> With editing off this renders without a '
            . 'card; turn editing on and it becomes a full card.</p>']);
    return true;
});

step('Showcase S1: activity with completion tracking', function () use ($dg, $SC) {
    $dg->create_module('page', ['course' => $SC, 'section' => 1,
        'name' => 'Tracked page (completion enabled)',
        'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1]);
    return true;
});

// --- Section 2: highlighted ----------------------------------------------
step('Showcase S2: highlighted section + activities', function () use ($dg, $SC, $DB) {
    $dg->create_module('page', ['course' => $SC, 'section' => 2, 'name' => 'Current week reading']);
    $dg->create_module('assign', ['course' => $SC, 'section' => 2, 'name' => 'Current week assignment']);
    $DB->set_field('course', 'marker', 2, ['id' => $SC]);
    return true;
});

// --- Section 3: hidden section -------------------------------------------
step('Showcase S3: hidden section with visible + hidden activities', function () use ($dg, $SC) {
    $dg->create_module('page', ['course' => $SC, 'section' => 3,
        'name' => 'Visible activity inside a hidden section']);
    $hidden = $dg->create_module('page', ['course' => $SC, 'section' => 3,
        'name' => 'Hidden activity inside a hidden section']);
    set_coursemodule_visible($hidden->cmid, 0);
    set_section_visible($SC, 3, 0);
    return true;
});

// --- Section 4: date-restricted section ----------------------------------
step('Showcase S4: section-level date restriction', function () use ($dg, $SC, $future, $DB) {
    $dg->create_module('page', ['course' => $SC, 'section' => 4,
        'name' => 'Content behind a section restriction']);
    $DB->set_field('course_sections', 'availability', date_restriction($future, true),
        ['course' => $SC, 'section' => 4]);
    return true;
});

// --- Section 5: subsections ----------------------------------------------
step('Showcase S5: populated subsection', function () use ($dg, $SC) {
    [$inst, $sec] = make_subsection($dg, $SC, 5, 'Populated subsection');
    $dg->create_module('page', ['course' => $SC, 'section' => $sec->section, 'name' => 'Nested page one']);
    $dg->create_module('page', ['course' => $SC, 'section' => $sec->section, 'name' => 'Nested page two']);
    $dg->create_module('forum', ['course' => $SC, 'section' => $sec->section, 'name' => 'Nested forum']);
    return true;
});

step('Showcase S5: empty subsection', function () use ($dg, $SC) {
    make_subsection($dg, $SC, 5, 'Empty subsection');
    return true;
});

step('Showcase S5: hidden subsection', function () use ($dg, $SC) {
    [$inst, $sec] = make_subsection($dg, $SC, 5, 'Hidden subsection');
    $dg->create_module('page', ['course' => $SC, 'section' => $sec->section, 'name' => 'Nested hidden content']);
    set_section_visible($SC, $sec->section, 0);
    return true;
});

// --- Section 6: intentionally empty ---------------------------------------

// --- Section 7: activity visibility matrix --------------------------------
step('Showcase S7: normal activity', function () use ($dg, $SC) {
    $dg->create_module('page', ['course' => $SC, 'section' => 7, 'name' => 'A. Normal visible activity']);
    return true;
});

step('Showcase S7: hidden from students', function () use ($dg, $SC) {
    $cm = $dg->create_module('page', ['course' => $SC, 'section' => 7, 'name' => 'B. Hidden from students']);
    set_coursemodule_visible($cm->cmid, 0);
    return true;
});

step('Showcase S7: stealth (available, not shown on course page)', function () use ($dg, $SC) {
    $cm = $dg->create_module('page', ['course' => $SC, 'section' => 7,
        'name' => 'C. Stealth — available but not shown on course page']);
    set_coursemodule_visible($cm->cmid, 1, 0);
    return true;
});

step('Showcase S7: restricted, greyed out with info', function () use ($dg, $SC, $future, $DB) {
    $cm = $dg->create_module('page', ['course' => $SC, 'section' => 7,
        'name' => 'D. Restricted — greyed out with info (eye open)']);
    $DB->set_field('course_modules', 'availability', date_restriction($future, true), ['id' => $cm->cmid]);
    return true;
});

step('Showcase S7: restricted, fully hidden', function () use ($dg, $SC, $future, $DB) {
    $cm = $dg->create_module('page', ['course' => $SC, 'section' => 7,
        'name' => 'E. Restricted — fully hidden from students (eye closed)']);
    $DB->set_field('course_modules', 'availability', date_restriction($future, false), ['id' => $cm->cmid]);
    return true;
});

// --- Section 8 ------------------------------------------------------------
step('Showcase S8: extra section content', function () use ($dg, $SC) {
    $dg->create_module('page', ['course' => $SC, 'section' => 8, 'name' => 'Content in the last section']);
    return true;
});

// --- Section summary with an image, to show .summarytext styling ----------
step('Showcase S1: section summary text', function () use ($SC, $DB) {
    $DB->set_field('course_sections', 'summary',
        '<p>Section summary text renders in the muted <code>.summarytext</code> style '
        . 'introduced with the new depth model.</p>',
        ['course' => $SC, 'section' => 1]);
    $DB->set_field('course_sections', 'summaryformat', FORMAT_HTML, ['course' => $SC, 'section' => 1]);
    return true;
});

// --- Enrolments -----------------------------------------------------------
$roles = $DB->get_records_menu('role', null, '', 'shortname, id');
$enrolplan = [
    6 => 'editingteacher',   // educator_busy
    3 => 'editingteacher',   // educator (previously had no enrolments at all)
    8 => 'student',          // student_busy
    9 => 'student',          // bio_ava
    4 => 'student',          // student (previously had no enrolments at all)
];
foreach ($enrolplan as $userid => $roleshort) {
    step("Enrol user {$userid} as {$roleshort}", function () use ($dg, $SC, $userid, $roleshort, $roles) {
        $dg->enrol_user($userid, $SC, $roles[$roleshort]);
        return true;
    });
}

rebuild_course_cache($SC, true);
mtrace('Rebuilt showcase course cache.');

// ---------------------------------------------------------------------------
mtrace("\n=== Summary ===");
foreach ($log as $l) {
    mtrace($l);
}
mtrace("\nShowcase course id: {$SC}   URL: {$CFG->wwwroot}/course/view.php?id={$SC}");
mtrace("Biology 101 URL:    {$CFG->wwwroot}/course/view.php?id={$BIO_COURSE}");
$subcount = $DB->count_records('course_sections', ['course' => $BIO_COURSE, 'component' => 'mod_subsection']);
mtrace("Biology 101 now has {$subcount} subsections.");
$sccount = $DB->count_records('course_sections', ['course' => $SC, 'component' => 'mod_subsection']);
mtrace("Showcase has {$sccount} subsections.");
if ($problems) {
    mtrace("\n" . count($problems) . ' PROBLEM(S):');
    foreach ($problems as $p) {
        mtrace('  - ' . $p);
    }
} else {
    mtrace("\nNo problems.");
}
