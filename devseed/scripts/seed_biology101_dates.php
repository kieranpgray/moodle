<?php
/**
 * Adds a "Deadline & date states" section to the Biology 101 mock course (id 15),
 * demonstrating every date-driven state across the activity types that have dates,
 * with the course itself set to "in progress" (now sits mid-course).
 *
 * States covered: not-yet-open, open, due-soon, overdue, closed (past cut-off),
 * submitted-on-time, submitted-late, graded/ungraded, per-user override,
 * per-group override, individual extension, grading-due, completion-expected/overdue,
 * date-based access restrictions (available from / until).
 *
 * Idempotent (all modules carry a 'dates_' idnumber prefix and are recreated).
 *   php biology101_dates.php
 */

define('CLI_SCRIPT', true);
require('/var/www/html/public/config.php');
global $CFG, $DB;
$CFG->noemailever = true;
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->libdir . '/completionlib.php');

$COURSEID = 15;
$course = $DB->get_record('course', ['id' => $COURSEID], '*', MUST_EXIST);
if (!in_array($course->shortname, ['MYOV-T-02', 'BIO101-MOCK'])) {
    cli_error("Refusing to run on course {$COURSEID} ('{$course->shortname}').");
}
$dg = \core\test\phpunit\phpunit_util::get_data_generator();
\core\session\manager::set_user(get_admin());

$NOW = time();
$D = DAYSECS;
function bio_log(string $m): void {
    cli_writeln('  ' . $m);
}
function bio_mod($dg, $course, string $modname, string $idnumber, array $record) {
    global $DB;
    foreach ($DB->get_records('course_modules', ['course' => $course->id, 'idnumber' => $idnumber]) as $cm) {
        course_delete_module($cm->id);
    }
    $record['course'] = $course->id;
    $record['idnumber'] = $idnumber;
    return $dg->get_plugin_generator('mod_' . $modname)->create_instance($record);
}
function dates_restrict_from(int $ts): string {
    return json_encode(['op' => '&', 'c' => [['type' => 'date', 'd' => '>=', 't' => $ts]], 'showc' => [true]]);
}
function dates_restrict_until(int $ts): string {
    return json_encode(['op' => '&', 'c' => [['type' => 'date', 'd' => '<', 't' => $ts]], 'showc' => [true]]);
}

// People.
$busy = $DB->get_record('user', ['username' => 'student_busy'], '*', MUST_EXIST);
$ava = $DB->get_record('user', ['username' => 'bio_ava']);
$liam = $DB->get_record('user', ['username' => 'bio_liam']);
$noah = $DB->get_record('user', ['username' => 'bio_noah']);
$groupa = $DB->get_record('groups', ['courseid' => $COURSEID, 'idnumber' => 'LABA'], '*', MUST_EXIST);
// Make sure the focus student is in the overridden group so group overrides are visible to them.
if (!$DB->record_exists('groups_members', ['groupid' => $groupa->id, 'userid' => $busy->id])) {
    $dg->create_group_member(['groupid' => $groupa->id, 'userid' => $busy->id]);
}

$assigngen = $dg->get_plugin_generator('mod_assign');
$quizgen = $dg->get_plugin_generator('mod_quiz');

// ---------------------------------------------------------------------------
// 1. Course -> in progress + show activity dates.
// ---------------------------------------------------------------------------
cli_heading('Course dates');
$course->startdate = $NOW - 42 * $D;
$course->enddate = $NOW + 42 * $D;
$course->showactivitydates = 1;
$course->enablecompletion = 1;
update_course($course);
bio_log('course set in-progress (started 6 weeks ago, ends in 6 weeks) + activity dates shown');

// ---------------------------------------------------------------------------
// 2. Dedicated section.
// ---------------------------------------------------------------------------
$sectionname = 'Deadline & date states';
$section = $DB->get_record('course_sections', ['course' => $COURSEID, 'name' => $sectionname]);
if (!$section) {
    $section = course_create_section($course, 0);
    $DB->set_field('course_sections', 'name', $sectionname, ['id' => $section->id]);
    $DB->set_field('course_sections', 'summary',
        '<p>Every date-driven state, with the course treated as in progress (now = mid-course). '
        . 'Log in as <strong>student_busy</strong> to see the learner states; as <strong>educator_busy</strong> '
        . 'for grading-due and override management.</p>', ['id' => $section->id]);
    $DB->set_field('course_sections', 'summaryformat', FORMAT_HTML, ['id' => $section->id]);
}
$sec = $section->section;
bio_log('section #' . $sec . ' "' . $sectionname . '"');

$label = function (string $text) use ($dg, $course, $sec) {
    static $n = 0;
    $n++;
    bio_mod($dg, $course, 'label', 'dates_label_' . $n, ['section' => $sec, 'intro' => $text, 'introformat' => FORMAT_HTML]);
};

// ===========================================================================
// ASSIGNMENTS — the richest date model.
// ===========================================================================
cli_heading('Assignments');
$label('<h4>📝 Assignments — open / due / cut-off / overrides / extensions</h4>');
$assignbase = ['section' => $sec, 'introformat' => FORMAT_HTML, 'grade' => 100,
    'assignsubmission_onlinetext_enabled' => 1, 'assignsubmission_file_enabled' => 0];

$a_notopen = bio_mod($dg, $course, 'assign', 'dates_assign_notopen', $assignbase + [
    'name' => 'A1 · Not yet open (opens in 3 days)',
    'intro' => 'Submissions have not opened yet.',
    'allowsubmissionsfromdate' => $NOW + 3 * $D, 'duedate' => $NOW + 17 * $D, 'cutoffdate' => $NOW + 24 * $D]);

$a_open = bio_mod($dg, $course, 'assign', 'dates_assign_open', $assignbase + [
    'name' => 'A2 · Open now, due in 10 days',
    'intro' => 'Open and accepting submissions; due date comfortably in the future.',
    'allowsubmissionsfromdate' => $NOW - 7 * $D, 'duedate' => $NOW + 10 * $D]);

$a_duesoon = bio_mod($dg, $course, 'assign', 'dates_assign_duesoon', $assignbase + [
    'name' => 'A3 · Open now, due TOMORROW',
    'intro' => 'Open, but the due date is imminent.',
    'allowsubmissionsfromdate' => $NOW - 7 * $D, 'duedate' => $NOW + 1 * $D]);

$a_overdue = bio_mod($dg, $course, 'assign', 'dates_assign_overdue', $assignbase + [
    'name' => 'A4 · Overdue, not submitted (past due, before cut-off)',
    'intro' => 'Past the due date but before the cut-off — late submissions still allowed. Focus student has NOT submitted.',
    'allowsubmissionsfromdate' => $NOW - 14 * $D, 'duedate' => $NOW - 2 * $D, 'cutoffdate' => $NOW + 5 * $D,
    'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionsubmit' => 1]);

$a_closed = bio_mod($dg, $course, 'assign', 'dates_assign_closed', $assignbase + [
    'name' => 'A5 · Closed (past cut-off — no more submissions)',
    'intro' => 'The cut-off date has passed; submissions are locked.',
    'allowsubmissionsfromdate' => $NOW - 30 * $D, 'duedate' => $NOW - 10 * $D, 'cutoffdate' => $NOW - 2 * $D]);

$a_ontime = bio_mod($dg, $course, 'assign', 'dates_assign_ontime', $assignbase + [
    'name' => 'A6 · Submitted on time + graded',
    'intro' => 'Due date in the future; focus student submitted before it and has been graded.',
    'allowsubmissionsfromdate' => $NOW - 14 * $D, 'duedate' => $NOW + 6 * $D]);

$a_late = bio_mod($dg, $course, 'assign', 'dates_assign_late', $assignbase + [
    'name' => 'A7 · Submitted LATE (after due, before cut-off)',
    'intro' => 'Due date has passed; focus student submitted late (flagged as X days late).',
    'allowsubmissionsfromdate' => $NOW - 20 * $D, 'duedate' => $NOW - 4 * $D, 'cutoffdate' => $NOW + 10 * $D]);

$a_uoverride = bio_mod($dg, $course, 'assign', 'dates_assign_uoverride', $assignbase + [
    'name' => 'A8 · Closed for the class, but USER override extends it',
    'intro' => 'Base dates are in the past (closed for everyone), but student_busy has a personal override keeping it open.',
    'allowsubmissionsfromdate' => $NOW - 10 * $D, 'duedate' => $NOW - 3 * $D, 'cutoffdate' => $NOW - 1 * $D]);

$a_goverride = bio_mod($dg, $course, 'assign', 'dates_assign_goverride', $assignbase + [
    'name' => 'A9 · GROUP override (Lab Group A gets extra time)',
    'intro' => 'Base due date has just passed, but Lab Group A has a group override extending the deadline.',
    'allowsubmissionsfromdate' => $NOW - 10 * $D, 'duedate' => $NOW - 1 * $D, 'cutoffdate' => $NOW - 1 * $D]);

$a_ext = bio_mod($dg, $course, 'assign', 'dates_assign_extension', $assignbase + [
    'name' => 'A10 · Individual EXTENSION granted',
    'intro' => 'Closed for the class, but the teacher granted student_busy an individual extension.',
    'allowsubmissionsfromdate' => $NOW - 14 * $D, 'duedate' => $NOW - 2 * $D, 'cutoffdate' => $NOW - 1 * $D]);

$a_grading = bio_mod($dg, $course, 'assign', 'dates_assign_grading', $assignbase + [
    'name' => 'A11 · Grading due (teacher-facing) — submissions awaiting marking',
    'intro' => 'Due date passed; a grading-due date is set and submissions are waiting to be marked.',
    'allowsubmissionsfromdate' => $NOW - 12 * $D, 'duedate' => $NOW - 4 * $D, 'gradingduedate' => $NOW + 2 * $D]);

$a_compover = bio_mod($dg, $course, 'assign', 'dates_assign_compoverdue', $assignbase + [
    'name' => 'A12 · Completion expected date has passed (overdue completion)',
    'intro' => 'Activity completion was expected 2 days ago; the focus student has not completed it.',
    'allowsubmissionsfromdate' => $NOW - 10 * $D, 'duedate' => $NOW + 20 * $D,
    'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionsubmit' => 1, 'completionexpected' => $NOW - 2 * $D]);

bio_log('created 12 assignment date-state variants');

// ---- Assignment submissions / grades / overrides / extensions ----
$submit = function ($cm, $user, string $text = 'My submission for this assignment.') use ($assigngen) {
    $assigngen->create_submission([
        'cmid' => $cm->cmid, 'userid' => $user->id, 'onlinetext' => $text,
        'status' => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
    ]);
    \core\session\manager::set_user(get_admin());
};
$grade = function ($cm, $user, float $g) use ($DB) {
    [$course, $cminfo] = get_course_and_cm_from_cmid($cm->cmid, 'assign');
    $assign = new assign(context_module::instance($cm->cmid), $cminfo, $course);
    $gd = (object)['grade' => $g, 'attemptnumber' => -1, 'addattempt' => 0, 'workflowstate' => '',
        'sendstudentnotifications' => 0, 'applytoall' => 0];
    $assign->save_grade($user->id, $gd);
    \core\session\manager::set_user(get_admin());
};

// A6: on-time submission + grade.
$submit($a_ontime, $busy);
$grade($a_ontime, $busy, 88);
if ($ava) {
    $submit($a_ontime, $ava);
    $grade($a_ontime, $ava, 93);
}
// A7: late submission (now > duedate) — not graded yet.
$submit($a_late, $busy, 'Sorry this is late — here is my enzyme write-up.');
if ($noah) {
    $submit($a_late, $noah);
}
// A11: submissions waiting for marking.
if ($ava) {
    $submit($a_grading, $ava);
}
if ($liam) {
    $submit($a_grading, $liam);
}
// A8: user override for student_busy (keeps it open for them).
$assigngen->create_override([
    'assignid' => $a_uoverride->id, 'userid' => $busy->id,
    'allowsubmissionsfromdate' => $NOW - 10 * $D, 'duedate' => $NOW + 10 * $D, 'cutoffdate' => $NOW + 20 * $D,
]);
$submit($a_uoverride, $busy, 'Submitting under my personal extension.');
// A9: group override for Lab Group A.
$assigngen->create_override([
    'assignid' => $a_goverride->id, 'groupid' => $groupa->id,
    'allowsubmissionsfromdate' => $NOW - 10 * $D, 'duedate' => $NOW + 14 * $D, 'cutoffdate' => $NOW + 21 * $D,
]);
// A10: individual extension for student_busy.
$assigngen->create_extension([
    'cmid' => $a_ext->cmid, 'userid' => $busy->id, 'extensionduedate' => $NOW + 7 * $D,
]);
bio_log('seeded submissions, grades, 2 overrides (user+group) and 1 extension');

// ===========================================================================
// QUIZZES.
// ===========================================================================
cli_heading('Quizzes');
$label('<h4>❓ Quizzes — open / close windows, time limit, overrides</h4>');
$quizbase = ['section' => $sec, 'introformat' => FORMAT_HTML, 'grade' => 10];

$q_notopen = bio_mod($dg, $course, 'quiz', 'dates_quiz_notopen', $quizbase + [
    'name' => 'Q1 · Not yet open (opens in 3 days)',
    'intro' => 'This quiz has not opened yet.', 'timeopen' => $NOW + 3 * $D, 'timeclose' => $NOW + 17 * $D]);
$q_open = bio_mod($dg, $course, 'quiz', 'dates_quiz_open', $quizbase + [
    'name' => 'Q2 · Open now (closes in 10 days)',
    'intro' => 'Open and available.', 'timeopen' => $NOW - 2 * $D, 'timeclose' => $NOW + 10 * $D]);
$q_closed = bio_mod($dg, $course, 'quiz', 'dates_quiz_closed', $quizbase + [
    'name' => 'Q3 · Closed (closed 2 days ago)',
    'intro' => 'The close date has passed.', 'timeopen' => $NOW - 20 * $D, 'timeclose' => $NOW - 2 * $D]);
$q_timed = bio_mod($dg, $course, 'quiz', 'dates_quiz_timed', $quizbase + [
    'name' => 'Q4 · Open now, 30-minute time limit, auto-submit',
    'intro' => 'Open with a time limit; unfinished attempts are auto-submitted.',
    'timeopen' => $NOW - 1 * $D, 'timeclose' => $NOW + 7 * $D, 'timelimit' => 1800, 'overduehandling' => 'autosubmit']);
$q_uoverride = bio_mod($dg, $course, 'quiz', 'dates_quiz_uoverride', $quizbase + [
    'name' => 'Q5 · Closed for the class, USER override reopens it',
    'intro' => 'Base close date has passed, but student_busy has a personal override.',
    'timeopen' => $NOW - 20 * $D, 'timeclose' => $NOW - 1 * $D]);
$q_goverride = bio_mod($dg, $course, 'quiz', 'dates_quiz_goverride', $quizbase + [
    'name' => 'Q6 · GROUP override (Lab Group A window extended)',
    'intro' => 'Base close date has passed, but Lab Group A has an extended window.',
    'timeopen' => $NOW - 20 * $D, 'timeclose' => $NOW - 1 * $D]);

$quizgen->create_override([
    'quiz' => $q_uoverride->id, 'userid' => $busy->id,
    'timeopen' => $NOW - 1 * $D, 'timeclose' => $NOW + 14 * $D, 'attempts' => 3,
]);
$quizgen->create_override([
    'quiz' => $q_goverride->id, 'groupid' => $groupa->id,
    'timeopen' => $NOW - 1 * $D, 'timeclose' => $NOW + 14 * $D,
]);
bio_log('created 6 quiz date-state variants (+ user & group overrides)');

// ===========================================================================
// OTHER TIMED ACTIVITIES.
// ===========================================================================
cli_heading('Other timed activities');
$label('<h4>⏳ Other activities with open/close windows</h4>');

bio_mod($dg, $course, 'choice', 'dates_choice_open', [
    'section' => $sec, 'introformat' => FORMAT_HTML, 'name' => 'Choice · Open now (closes in 5 days)',
    'intro' => 'A poll that is currently open.', 'option' => ['Option A', 'Option B', 'Option C'],
    'timeopen' => $NOW - 2 * $D, 'timeclose' => $NOW + 5 * $D, 'showresults' => 1]);
bio_mod($dg, $course, 'choice', 'dates_choice_closed', [
    'section' => $sec, 'introformat' => FORMAT_HTML, 'name' => 'Choice · Closed (closed 2 days ago)',
    'intro' => 'A poll whose window has closed.', 'option' => ['Yes', 'No'],
    'timeopen' => $NOW - 20 * $D, 'timeclose' => $NOW - 2 * $D, 'showresults' => 1]);
bio_mod($dg, $course, 'feedback', 'dates_feedback_open', [
    'section' => $sec, 'introformat' => FORMAT_HTML, 'name' => 'Feedback · Open window (now → +10 days)',
    'intro' => 'A feedback survey open for a limited window.',
    'timeopen' => $NOW - 2 * $D, 'timeclose' => $NOW + 10 * $D]);
bio_mod($dg, $course, 'lesson', 'dates_lesson_open', [
    'section' => $sec, 'introformat' => FORMAT_HTML, 'name' => 'Lesson · Available now, deadline in 7 days',
    'intro' => 'A lesson with an availability window and a deadline.',
    'available' => $NOW - 2 * $D, 'deadline' => $NOW + 7 * $D]);
bio_mod($dg, $course, 'lesson', 'dates_lesson_closed', [
    'section' => $sec, 'introformat' => FORMAT_HTML, 'name' => 'Lesson · Deadline passed',
    'intro' => 'A lesson whose deadline is in the past.',
    'available' => $NOW - 20 * $D, 'deadline' => $NOW - 2 * $D]);
bio_mod($dg, $course, 'data', 'dates_data_open', [
    'section' => $sec, 'introformat' => FORMAT_HTML, 'name' => 'Database · Available + view windows set',
    'intro' => 'A database with availability and view windows.',
    'timeavailablefrom' => $NOW - 2 * $D, 'timeavailableto' => $NOW + 10 * $D,
    'timeviewfrom' => $NOW - 2 * $D, 'timeviewto' => $NOW + 30 * $D]);
bio_mod($dg, $course, 'workshop', 'dates_workshop_sub', [
    'section' => $sec, 'introformat' => FORMAT_HTML, 'name' => 'Workshop · In submission phase (assessment opens later)',
    'intro' => 'Submission phase is open now; the assessment phase opens after it closes.',
    'phase' => 20, // PHASE_SUBMISSION
    'submissionstart' => $NOW - 3 * $D, 'submissionend' => $NOW + 4 * $D,
    'assessmentstart' => $NOW + 4 * $D, 'assessmentend' => $NOW + 11 * $D]);
bio_mod($dg, $course, 'forum', 'dates_forum_due', [
    'section' => $sec, 'introformat' => FORMAT_HTML, 'type' => 'general',
    'name' => 'Forum · Posting due in 5 days (cut-off +12)',
    'intro' => 'A forum with a due date and a later cut-off for posts.',
    'duedate' => $NOW + 5 * $D, 'cutoffdate' => $NOW + 12 * $D]);
bio_mod($dg, $course, 'forum', 'dates_forum_overdue', [
    'section' => $sec, 'introformat' => FORMAT_HTML, 'type' => 'general',
    'name' => 'Forum · Posting overdue (due passed, cut-off in 4 days)',
    'intro' => 'Past the posting due date but before the cut-off.',
    'duedate' => $NOW - 3 * $D, 'cutoffdate' => $NOW + 4 * $D]);
bio_log('created 9 other timed activities (choice, feedback, lesson x2, data, workshop, forum x2)');

// ===========================================================================
// COMPLETION & DATE-BASED ACCESS RESTRICTIONS.
// ===========================================================================
cli_heading('Completion & access restrictions');
$label('<h4>✅ Completion states & 🔒 date-based access restrictions</h4>');

$p_done = bio_mod($dg, $course, 'page', 'dates_page_completed', [
    'section' => $sec, 'introformat' => FORMAT_HTML, 'name' => 'Page · Completed (green tick)',
    'intro' => 'The focus student has completed this by viewing it.',
    'content' => '<p>You have viewed this page.</p>', 'contentformat' => FORMAT_HTML,
    'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1]);
bio_mod($dg, $course, 'page', 'dates_page_expected_future', [
    'section' => $sec, 'introformat' => FORMAT_HTML, 'name' => 'Page · Completion expected in 5 days (not done)',
    'intro' => 'Completion is expected soon; not completed yet.',
    'content' => '<p>Complete me before the expected date.</p>', 'contentformat' => FORMAT_HTML,
    'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1, 'completionexpected' => $NOW + 5 * $D]);
bio_mod($dg, $course, 'page', 'dates_page_restricted_until', [
    'section' => $sec, 'introformat' => FORMAT_HTML, 'name' => 'Page · Restricted — available from a future date',
    'intro' => 'Locked until a future date.', 'content' => '<p>Secret.</p>', 'contentformat' => FORMAT_HTML,
    'availability' => dates_restrict_from($NOW + 5 * $D)]);
bio_mod($dg, $course, 'page', 'dates_page_restricted_from_past', [
    'section' => $sec, 'introformat' => FORMAT_HTML, 'name' => 'Page · Was restricted until a past date (now open)',
    'intro' => 'Became available a few days ago.', 'content' => '<p>Now visible.</p>', 'contentformat' => FORMAT_HTML,
    'availability' => dates_restrict_from($NOW - 3 * $D)]);
bio_mod($dg, $course, 'page', 'dates_page_restricted_until_future', [
    'section' => $sec, 'introformat' => FORMAT_HTML, 'name' => 'Page · Available UNTIL a future date',
    'intro' => 'Accessible now but only until a future date.', 'content' => '<p>Grab it while you can.</p>',
    'contentformat' => FORMAT_HTML, 'availability' => dates_restrict_until($NOW + 5 * $D)]);
bio_log('created 5 completion / access-restriction pages');

// Mark the "completed" page complete for the focus student; leave overdue ones incomplete.
$completion = new completion_info($course);
if ($completion->is_enabled()) {
    $cm = get_fast_modinfo($course)->get_cm($p_done->cmid);
    $completion->update_state($cm, COMPLETION_COMPLETE, $busy->id, true);
    bio_log('marked "Page · Completed" complete for student_busy');
}

rebuild_course_cache($COURSEID, true);
cli_writeln('DONE — date & deadline state matrix built.');
