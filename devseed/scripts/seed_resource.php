<?php
/**
 * Adds the file-resource activity that renders the pale "type" badge
 * (.activitybadge.badge-none). Split out because the resource generator
 * needs a logged-in user for file handling.
 */

define('CLI_SCRIPT', true);
require('/var/www/html/public/config.php');
require_once($CFG->dirroot . '/course/lib.php');

global $DB, $CFG;

// The resource generator writes files, so it needs a current user context.
\core\cron::setup_user(get_admin());

$course = $DB->get_record('course', ['shortname' => 'DEPTH-DEMO'], '*', MUST_EXIST);
$dg = \core\test\phpunit\phpunit_util::get_data_generator();

$res = $dg->create_module('resource', [
    'course' => $course->id,
    'section' => 1,
    'name' => 'Lab safety handout (file — shows type badge)',
]);

// "Show type" is what renders the pale bordered badge-none pill on the card.
$DB->set_field('resource', 'displayoptions',
    serialize(['printintro' => 1, 'showsize' => 1, 'showtype' => 1]),
    ['id' => $res->id]);

rebuild_course_cache($course->id, true);

mtrace("Created file resource cmid {$res->cmid} in course {$course->id} section 1 with showtype enabled.");
