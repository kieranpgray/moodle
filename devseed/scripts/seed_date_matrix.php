<?php
/**
 * Biology 101 — complete activity date matrix.
 *
 * Creates one section covering every date field on every date-bearing activity
 * module, across every temporal state (far past / overdue / imminent / open /
 * near future / far future), plus a "maximum" variant per module with all of
 * its date fields set at once.
 *
 * Idempotent and re-runnable: wipes and rebuilds its own section, recomputing
 * every date relative to "now", so the labels never drift out of sync.
 *
 * Run: php /tmp/seed_date_matrix.php [courseid]
 */

define('CLI_SCRIPT', true);
require('/var/www/html/public/config.php');
require_once($CFG->dirroot . '/course/lib.php');

global $DB, $CFG;

// Some generators write files and need a current user.
\core\cron::setup_user(get_admin());

$courseid = (int) ($argv[1] ?? 15);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$dg = \core\test\phpunit\phpunit_util::get_data_generator();

const SECTION_NAME = 'Date matrix — every date field, every state';

$now = time();
// Temporal anchors.
$FAR_PAST = $now - (60 * DAYSECS);
$PAST     = $now - (14 * DAYSECS);
$RECENT   = $now - (2 * DAYSECS);
$JUST     = $now - HOURSECS;
$SOON     = $now + DAYSECS;
$NEAR     = $now + (7 * DAYSECS);
$FAR      = $now + (60 * DAYSECS);

$log = [];
$problems = [];

function step(string $what, callable $fn) {
    global $log, $problems;
    try {
        $fn();
        $log[] = "  OK   {$what}";
    } catch (Throwable $e) {
        $problems[] = "{$what}: " . $e->getMessage();
        $log[] = "  FAIL {$what} -- " . $e->getMessage();
    }
}

// --- Rebuild the matrix section from scratch ------------------------------
$section = $DB->get_record('course_sections', ['course' => $courseid, 'name' => SECTION_NAME]);
if ($section) {
    mtrace("Existing matrix section found (section {$section->section}) — clearing it.");
    $modinfo = get_fast_modinfo($courseid);
    foreach (($modinfo->sections[$section->section] ?? []) as $cmid) {
        course_delete_module($cmid);
    }
    rebuild_course_cache($courseid, true);
} else {
    $section = course_create_section($courseid);
    $DB->set_field('course_sections', 'name', SECTION_NAME, ['id' => $section->id]);
    mtrace("Created new matrix section (section {$section->section}).");
}
$SN = $section->section;

/** Create a module in the matrix section and apply date fields to its instance. */
function make($dg, string $modname, int $courseid, int $sectionnum, string $name, array $dates = [], array $opts = []) {
    global $DB;
    $params = array_merge(['course' => $courseid, 'section' => $sectionnum, 'name' => $name], $opts);
    $inst = $dg->create_module($modname, $params);
    if ($dates) {
        $rec = (object) array_merge(['id' => $inst->id], $dates);
        $DB->update_record($modname, $rec);
    }
    return $inst;
}

// =========================================================================
// assign — allowsubmissionsfromdate, duedate, cutoffdate, gradingduedate, timelimit
// =========================================================================
step('assign · not yet open', fn() => make($dg, 'assign', $courseid, $SN,
    'ASSIGN · Not yet open (opens in 7 days)',
    ['allowsubmissionsfromdate' => $NEAR, 'duedate' => $FAR, 'cutoffdate' => $FAR + DAYSECS]));

step('assign · open, due tomorrow', fn() => make($dg, 'assign', $courseid, $SN,
    'ASSIGN · Open now, due TOMORROW',
    ['allowsubmissionsfromdate' => $PAST, 'duedate' => $SOON, 'cutoffdate' => $NEAR]));

step('assign · overdue, still accepting', fn() => make($dg, 'assign', $courseid, $SN,
    'ASSIGN · Overdue (2 days) — still accepting until cut-off',
    ['allowsubmissionsfromdate' => $PAST, 'duedate' => $RECENT, 'cutoffdate' => $NEAR]));

step('assign · closed past cutoff', fn() => make($dg, 'assign', $courseid, $SN,
    'ASSIGN · Closed — past cut-off, no more submissions',
    ['allowsubmissionsfromdate' => $FAR_PAST, 'duedate' => $PAST, 'cutoffdate' => $RECENT]));

step('assign · MAXIMUM all five date fields', fn() => make($dg, 'assign', $courseid, $SN,
    'ASSIGN · MAXIMUM — from + due + cut-off + grading due + 90min time limit',
    ['allowsubmissionsfromdate' => $PAST, 'duedate' => $SOON, 'cutoffdate' => $NEAR,
     'gradingduedate' => $FAR, 'timelimit' => 90 * MINSECS]));

// =========================================================================
// quiz — timeopen, timeclose, timelimit
// =========================================================================
step('quiz · not yet open', fn() => make($dg, 'quiz', $courseid, $SN,
    'QUIZ · Not yet open (opens in 7 days)',
    ['timeopen' => $NEAR, 'timeclose' => $FAR]));

step('quiz · open, closes soon', fn() => make($dg, 'quiz', $courseid, $SN,
    'QUIZ · Open now, closes TOMORROW',
    ['timeopen' => $PAST, 'timeclose' => $SOON]));

step('quiz · closed', fn() => make($dg, 'quiz', $courseid, $SN,
    'QUIZ · Closed (closed 2 days ago)',
    ['timeopen' => $FAR_PAST, 'timeclose' => $RECENT]));

step('quiz · MAXIMUM open+close+timelimit', fn() => make($dg, 'quiz', $courseid, $SN,
    'QUIZ · MAXIMUM — open + close + 45min time limit',
    ['timeopen' => $JUST, 'timeclose' => $NEAR, 'timelimit' => 45 * MINSECS]));

// =========================================================================
// choice / feedback — timeopen, timeclose
// =========================================================================
foreach (['choice' => 'CHOICE', 'feedback' => 'FEEDBACK'] as $mod => $tag) {
    step("{$mod} · not yet open", fn() => make($dg, $mod, $courseid, $SN,
        "{$tag} · Not yet open (opens in 7 days)", ['timeopen' => $NEAR, 'timeclose' => $FAR]));
    step("{$mod} · open now", fn() => make($dg, $mod, $courseid, $SN,
        "{$tag} · MAXIMUM — open now, closes in 7 days", ['timeopen' => $PAST, 'timeclose' => $NEAR]));
    step("{$mod} · closed", fn() => make($dg, $mod, $courseid, $SN,
        "{$tag} · Closed (closed 2 days ago)", ['timeopen' => $FAR_PAST, 'timeclose' => $RECENT]));
}

// =========================================================================
// lesson — available, deadline, timelimit
// =========================================================================
step('lesson · not yet available', fn() => make($dg, 'lesson', $courseid, $SN,
    'LESSON · Not yet available (opens in 7 days)',
    ['available' => $NEAR, 'deadline' => $FAR]));

step('lesson · MAXIMUM available+deadline+timelimit', fn() => make($dg, 'lesson', $courseid, $SN,
    'LESSON · MAXIMUM — available + deadline + 30min time limit',
    ['available' => $PAST, 'deadline' => $NEAR, 'timelimit' => 30 * MINSECS]));

step('lesson · past deadline', fn() => make($dg, 'lesson', $courseid, $SN,
    'LESSON · Past deadline (closed 2 days ago)',
    ['available' => $FAR_PAST, 'deadline' => $RECENT]));

// =========================================================================
// data — timeavailablefrom/to, timeviewfrom/to, assesstimestart/finish
// =========================================================================
step('data · MAXIMUM all six date fields', fn() => make($dg, 'data', $courseid, $SN,
    'DATABASE · MAXIMUM — entry window + view window + rating window',
    ['timeavailablefrom' => $PAST, 'timeavailableto' => $NEAR,
     'timeviewfrom' => $PAST, 'timeviewto' => $FAR,
     'assesstimestart' => $PAST, 'assesstimefinish' => $NEAR, 'assessed' => 1, 'scale' => 100]));

step('data · entry window closed, view open', fn() => make($dg, 'data', $courseid, $SN,
    'DATABASE · Entry window closed, viewing still open',
    ['timeavailablefrom' => $FAR_PAST, 'timeavailableto' => $RECENT,
     'timeviewfrom' => $FAR_PAST, 'timeviewto' => $FAR]));

// =========================================================================
// forum — duedate, cutoffdate, assesstimestart/finish
// =========================================================================
step('forum · MAXIMUM due+cutoff+rating window', fn() => make($dg, 'forum', $courseid, $SN,
    'FORUM · MAXIMUM — due + cut-off + rating window',
    ['duedate' => $SOON, 'cutoffdate' => $NEAR,
     'assesstimestart' => $PAST, 'assesstimefinish' => $NEAR, 'assessed' => 1, 'scale' => 100]));

step('forum · past cutoff', fn() => make($dg, 'forum', $courseid, $SN,
    'FORUM · Past cut-off (no further posts)',
    ['duedate' => $PAST, 'cutoffdate' => $RECENT]));

// =========================================================================
// glossary — assesstimestart/finish
// =========================================================================
step('glossary · rating window open', fn() => make($dg, 'glossary', $courseid, $SN,
    'GLOSSARY · Rating window OPEN (closes in 7 days)',
    ['assesstimestart' => $PAST, 'assesstimefinish' => $NEAR, 'assessed' => 1, 'scale' => 100]));

step('glossary · rating window closed', fn() => make($dg, 'glossary', $courseid, $SN,
    'GLOSSARY · Rating window CLOSED (closed 2 days ago)',
    ['assesstimestart' => $FAR_PAST, 'assesstimefinish' => $RECENT, 'assessed' => 1, 'scale' => 100]));

// =========================================================================
// workshop — submissionstart/end, assessmentstart/end
// =========================================================================
step('workshop · submission phase open', fn() => make($dg, 'workshop', $courseid, $SN,
    'WORKSHOP · Submission phase OPEN (assessment later)',
    ['submissionstart' => $PAST, 'submissionend' => $SOON,
     'assessmentstart' => $NEAR, 'assessmentend' => $FAR]));

step('workshop · assessment phase open', fn() => make($dg, 'workshop', $courseid, $SN,
    'WORKSHOP · Assessment phase OPEN (submissions closed)',
    ['submissionstart' => $FAR_PAST, 'submissionend' => $RECENT,
     'assessmentstart' => $JUST, 'assessmentend' => $NEAR]));

step('workshop · all phases past', fn() => make($dg, 'workshop', $courseid, $SN,
    'WORKSHOP · MAXIMUM — all four phase dates, all in the past',
    ['submissionstart' => $FAR_PAST, 'submissionend' => $FAR_PAST + (5 * DAYSECS),
     'assessmentstart' => $FAR_PAST + (6 * DAYSECS), 'assessmentend' => $RECENT]));

// =========================================================================
// scorm — timeopen, timeclose  (none existed in this course)
// =========================================================================
step('scorm · open now', fn() => make($dg, 'scorm', $courseid, $SN,
    'SCORM · MAXIMUM — open now, closes in 7 days',
    ['timeopen' => $PAST, 'timeclose' => $NEAR]));

step('scorm · closed', fn() => make($dg, 'scorm', $courseid, $SN,
    'SCORM · Closed (closed 2 days ago)',
    ['timeopen' => $FAR_PAST, 'timeclose' => $RECENT]));

// =========================================================================
// wiki — editend
// =========================================================================
step('wiki · editing closes soon', fn() => make($dg, 'wiki', $courseid, $SN,
    'WIKI · Editing closes in 7 days', ['editend' => $NEAR]));

step('wiki · editing closed', fn() => make($dg, 'wiki', $courseid, $SN,
    'WIKI · Editing closed (2 days ago)', ['editend' => $RECENT]));

// =========================================================================
// completionexpected — applies to every module type
// =========================================================================
step('completionexpected · past and future', function () use ($dg, $courseid, $SN, $RECENT, $NEAR, $DB) {
    $a = make($dg, 'page', $courseid, $SN, 'PAGE · Expected completion was 2 days ago (overdue)',
        [], ['completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1]);
    $DB->set_field('course_modules', 'completionexpected', $RECENT, ['id' => $a->cmid]);
    $b = make($dg, 'page', $courseid, $SN, 'PAGE · Expected completion in 7 days',
        [], ['completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1]);
    $DB->set_field('course_modules', 'completionexpected', $NEAR, ['id' => $b->cmid]);
});

rebuild_course_cache($courseid, true);

// --- Report ---------------------------------------------------------------
mtrace("\n=== Result ===");
foreach ($log as $l) {
    mtrace($l);
}
$count = $DB->count_records_sql(
    "SELECT COUNT(*) FROM {course_modules} cm
       JOIN {course_sections} s ON s.id = cm.section
      WHERE cm.course = ? AND s.section = ?", [$courseid, $SN]);
mtrace("\nMatrix section {$SN} now holds {$count} activities.");
mtrace("Course URL: {$CFG->wwwroot}/course/view.php?id={$courseid}");
if ($problems) {
    mtrace("\n" . count($problems) . ' problem(s):');
    foreach ($problems as $p) {
        mtrace('  - ' . $p);
    }
} else {
    mtrace("\nNo problems.");
}
