<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Resolve the sticky footer / bottom navigation UX audit URLs for one course.
 *
 * Prints every screen inside a course that has a sticky footer, a bottom navigation
 * bar, or a page level save action, with real IDs substituted from the database so
 * the URLs can be opened directly.
 *
 * Screens are labelled with the pattern they use:
 *   A  bespoke core sticky footer          core\output\sticky_footer
 *   B  linear navigation sticky footer     core_courseformat supplementary_sticky_footer
 *   C  no footer, actions rendered in page  set_show_navigation_footer(false)
 *   D  legacy in page activity navigation   core_renderer::activity_navigation()
 *
 * @package     core
 * @subpackage  cli
 * @copyright   2026 Moodle UX audit
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');

$usage = "Resolve the sticky footer / bottom navigation audit URLs for a course.

Usage:
    # php footer_audit_urls.php --courseid=15
    # php footer_audit_urls.php --courseid=15 --wwwroot=https://localhost:9443
    # php footer_audit_urls.php --courseid=15 --pattern=C --role=student
    # php footer_audit_urls.php --courseid=15 --json > urls.json

Options:
    -h --help              Print this help.
    --courseid=<id>        Course to resolve URLs for. Required.
    --wwwroot=<url>        Base URL to prefix. Defaults to \$CFG->wwwroot.
    --pattern=<A|B|C|D>    Only show screens using this pattern. Comma separated.
    --role=<student|teacher>  Only show screens visible to this role.
    --json                 Emit JSON instead of a readable list.
";

[$options, $unrecognised] = cli_get_params([
    'help' => false,
    'courseid' => null,
    'wwwroot' => null,
    'pattern' => null,
    'role' => null,
    'json' => false,
], [
    'h' => 'help',
]);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode(PHP_EOL . '  ', $unrecognised)));
}

if ($options['help'] || empty($options['courseid'])) {
    cli_writeln($usage);
    exit(empty($options['courseid']) && !$options['help'] ? 1 : 0);
}

$courseid = (int) $options['courseid'];
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$coursecontext = context_course::instance($course->id);
$base = rtrim($options['wwwroot'] ?? $CFG->wwwroot, '/');

$patternfilter = $options['pattern']
    ? array_map('strtoupper', array_map('trim', explode(',', $options['pattern'])))
    : null;
$rolefilter = $options['role'] ? strtolower($options['role']) : null;

$modinfo = get_fast_modinfo($course);
$format = course_get_format($course);
$formatoptions = $format->get_format_options();
$linearnav = \core_courseformat\local\linearnavigationsettings::is_linear_navigation_enabled($course);

$rows = [];
$skipped = [];

/**
 * Record one resolved audit URL.
 *
 * @param string $pattern One of A, B, C, D.
 * @param string $role One of student, teacher, both.
 * @param string $screen Human readable screen name.
 * @param string $path Root relative path including query string.
 * @param string $actions What sits in the bottom action zone.
 */
function add_row(string $pattern, string $role, string $screen, string $path, string $actions): void {
    global $rows;
    $rows[] = [
        'pattern' => $pattern,
        'role' => $role,
        'screen' => $screen,
        'path' => $path,
        'actions' => $actions,
    ];
}

/**
 * Note a screen that could not be resolved, so the gap is visible rather than silent.
 *
 * @param string $screen Human readable screen name.
 * @param string $why What is missing from this course.
 */
function skip(string $screen, string $why): void {
    global $skipped;
    $skipped[] = "$screen — $why";
}

/**
 * Find the first course module of a given type, or null when the course has none.
 *
 * @param string $modname Module name, for example quiz.
 * @return cm_info|null
 */
function first_cm(string $modname): ?cm_info {
    global $modinfo;
    $instances = $modinfo->get_instances_of($modname);
    return $instances ? reset($instances) : null;
}

// Sample users, used by the screens that address a single participant.
$studentid = null;
$teacherid = null;
foreach (get_enrolled_users($coursecontext, '', 0, 'u.id', null, 0, 50) as $user) {
    if (is_null($teacherid) && has_capability('mod/assign:grade', $coursecontext, $user->id)) {
        $teacherid = $user->id;
    } else if (is_null($studentid) && !has_capability('moodle/course:manageactivities', $coursecontext, $user->id)) {
        $studentid = $user->id;
    }
}

// ---------------------------------------------------------------------------
// Course level screens. These need only the course id, so they always resolve.
// ---------------------------------------------------------------------------

add_row('A', 'teacher', 'Course page (bulk actions in edit mode)',
    "/course/view.php?id=$courseid", 'Select all, bulk move / duplicate / hide / delete, Cancel');
add_row('A', 'teacher', 'Course settings',
    "/course/edit.php?id=$courseid", 'Save and display, Cancel');
add_row('A', 'teacher', 'Reset course',
    "/course/reset.php?id=$courseid", 'Reset course (submit only, no Cancel)');
add_row('A', 'teacher', 'Grader report',
    "/grade/report/grader/index.php?id=$courseid", 'Paging bar, Save changes when editing is on');
add_row('A', 'teacher', 'Single view',
    "/grade/report/singleview/index.php?id=$courseid", 'Previous / Next user, bulk insert, Save');
add_row('A', 'teacher', 'Gradebook setup',
    "/grade/edit/tree/index.php?id=$courseid", 'Select all, bulk move / delete, Save changes');
add_row('A', 'teacher', 'Export grades',
    "/grade/export/xls/index.php?id=$courseid", 'Download');
add_row('A', 'teacher', 'Import grades',
    "/grade/import/csv/index.php?id=$courseid", 'Upload grades (submit only)');
add_row('C', 'teacher', 'Course backup wizard',
    "/backup/backup.php?id=$courseid", 'In page: Cancel, Next');
add_row('C', 'teacher', 'Course logs',
    "/report/log/index.php?id=$courseid", 'In page: Get these logs, Download');
add_row('C', 'teacher', 'Question banks in this course',
    "/question/banks.php?courseid=$courseid", 'In page: bank list actions');
add_row('C', 'teacher', 'Assign roles in the course',
    "/admin/roles/assign.php?contextid={$coursecontext->id}", 'In page: Add / Remove');
add_row('C', 'teacher', 'Override permissions',
    "/admin/roles/override.php?contextid={$coursecontext->id}", 'In page: Save changes, Cancel');
add_row('C', 'teacher', 'Check permissions',
    "/admin/roles/permissions.php?contextid={$coursecontext->id}", 'In page: per capability controls');

if ($studentid) {
    add_row('A', 'teacher', 'User report for one participant',
        "/grade/report/user/index.php?id=$courseid&userid=$studentid", 'Previous / Next user');
} else {
    skip('User report for one participant', 'no enrolled non editing participant found');
}

// ---------------------------------------------------------------------------
// Every activity landing page. Pattern B when linear navigation is on for this
// course, otherwise D for formats without a course index, otherwise no footer.
// ---------------------------------------------------------------------------

$landingpattern = $linearnav ? 'B' : ($format->uses_course_index() ? 'C' : 'D');
$landingactions = $linearnav
    ? 'Sticky footer: completion, Previous, Next / Back to course'
    : ($format->uses_course_index()
        ? 'No footer: linear navigation is off for this course'
        : 'In page: Previous, Jump to..., Next (legacy block)');

foreach ($modinfo->get_cms() as $cm) {
    if (!$cm->uservisible || empty($cm->url)) {
        continue;
    }
    add_row($landingpattern, 'both', "{$cm->modname}: {$cm->get_formatted_name()} — landing page",
        "/mod/{$cm->modname}/view.php?id={$cm->id}", $landingactions);
    add_row('A', 'teacher', "{$cm->modname}: {$cm->get_formatted_name()} — activity settings",
        "/course/modedit.php?update={$cm->id}", 'Save and return to course, Save and display, Cancel');
}

// ---------------------------------------------------------------------------
// Module specific screens.
// ---------------------------------------------------------------------------

if ($cm = first_cm('assign')) {
    $id = $cm->id;
    add_row('A', 'teacher', 'Assignment: submissions table (quick grading)',
        "/mod/assign/view.php?id=$id&action=grading",
        'Sticky footer: per page, paging bar, Notify students, Save');
    add_row('C', 'teacher', 'Assignment: grading app',
        "/mod/assign/view.php?id=$id&action=grader",
        'Bespoke panel: Save changes, Save and next, Reset');
    add_row('C', 'student', 'Assignment: edit submission',
        "/mod/assign/view.php?id=$id&action=editsubmission", 'In page: Save changes, Cancel');
    add_row('C', 'student', 'Assignment: confirm submit for grading',
        "/mod/assign/view.php?id=$id&action=submit", 'In page: Continue, Cancel');
    add_row('C', 'teacher', 'Assignment: overrides',
        "/mod/assign/overrides.php?cmid=$id&mode=user", 'In page: Add user override');

    $areaid = $DB->get_field('grading_areas', 'id', [
        'contextid' => $cm->context->id,
        'component' => 'mod_assign',
        'areaname' => 'submissions',
    ]);
    if ($areaid) {
        add_row('C', 'teacher', 'Assignment: advanced grading method',
            "/grade/grading/manage.php?areaid=$areaid", 'In page: method links');
        add_row('C', 'teacher', 'Assignment: define rubric',
            "/grade/grading/form/rubric/edit.php?areaid=$areaid",
            'In page: Save rubric and make it ready, Save as draft, Cancel');
    } else {
        add_row('C', 'teacher', 'Assignment: advanced grading method',
            "/grade/grading/manage.php?contextid={$cm->context->id}&component=mod_assign&area=submissions",
            'In page: method links (no grading area defined yet)');
        skip('Assignment: define rubric', 'no grading area row yet, set the grading method first');
    }
} else {
    skip('Assignment screens', 'no assign activity in this course');
}

if ($cm = first_cm('quiz')) {
    $id = $cm->id;
    add_row('C', 'teacher', 'Quiz: edit question layout',
        "/mod/quiz/edit.php?cmid=$id", 'In page: repaginate, add question, Save maximum grade');
    add_row('C', 'teacher', 'Quiz: grades setup',
        "/mod/quiz/editgrading.php?cmid=$id", 'In page: Save changes');
    add_row('C', 'teacher', 'Quiz: results',
        "/mod/quiz/report.php?id=$id&mode=overview", 'In page: bulk actions, Download, Regrade');
    add_row('C', 'teacher', 'Quiz: overrides',
        "/mod/quiz/overrides.php?cmid=$id&mode=user", 'In page: Add user override');

    $attempt = $DB->get_record_sql(
        "SELECT qa.id, qa.state
           FROM {quiz_attempts} qa
          WHERE qa.quiz = :quizid
       ORDER BY qa.id DESC",
        ['quizid' => $cm->instance],
        IGNORE_MULTIPLE
    );
    if ($attempt) {
        add_row('C', 'student', 'Quiz: attempt in progress',
            "/mod/quiz/attempt.php?attempt={$attempt->id}&page=0",
            'In page: Previous page, Next page / Finish attempt');
        add_row('C', 'student', 'Quiz: attempt summary before submitting',
            "/mod/quiz/summary.php?attempt={$attempt->id}",
            'In page: Return to attempt, Submit all and finish');
        add_row('C', 'student', 'Quiz: review a finished attempt',
            "/mod/quiz/review.php?attempt={$attempt->id}",
            'In page: Previous, Next, Finish review');
        if ($attempt->state !== 'inprogress') {
            skip('Quiz: attempt.php and summary.php',
                "attempt {$attempt->id} is '{$attempt->state}', start a new attempt to load those two live");
        }
    } else {
        skip('Quiz attempt, summary and review', 'no quiz attempts recorded yet, sit the quiz once');
    }
} else {
    skip('Quiz screens', 'no quiz activity in this course');
}

if ($cm = first_cm('forum')) {
    $id = $cm->id;
    $instance = $cm->instance;
    add_row('C', 'student', 'Forum: start a discussion',
        "/mod/forum/post.php?forum=$instance", 'In page: Post to forum, Cancel');
    add_row('C', 'teacher', 'Forum: subscribers',
        "/mod/forum/subscribers.php?id=$instance", 'In page: Add / Remove selected');
    add_row('C', 'teacher', 'Forum: summary report',
        "/mod/forum/report/summary/index.php?courseid=$courseid&forumid=$instance",
        'In page: bulk message, Download');

    $discussionid = $DB->get_field_sql(
        "SELECT id FROM {forum_discussions} WHERE forum = :forum ORDER BY id DESC",
        ['forum' => $instance],
        IGNORE_MULTIPLE
    );
    if ($discussionid) {
        add_row('B', 'both', 'Forum: a discussion',
            "/mod/forum/discuss.php?d=$discussionid",
            'Sticky footer: completion, Previous, Next, plus "Go to all discussions"');
    } else {
        skip('Forum discussion', 'this forum has no discussions yet');
    }
} else {
    skip('Forum screens', 'no forum activity in this course');
}

if ($cm = first_cm('glossary')) {
    add_row('C', 'student', 'Glossary: add an entry',
        "/mod/glossary/edit.php?cmid={$cm->id}", 'In page: Save changes, Cancel');
    add_row('C', 'teacher', 'Glossary: categories',
        "/mod/glossary/editcategories.php?id={$cm->id}", 'In page: Add category');
} else {
    skip('Glossary screens', 'no glossary activity in this course');
}

if ($cm = first_cm('data')) {
    $d = $cm->instance;
    add_row('C', 'student', 'Database: add an entry',
        "/mod/data/edit.php?d=$d", 'In page: Save and view, Save and add another, Cancel');
    add_row('C', 'teacher', 'Database: templates',
        "/mod/data/templates.php?d=$d&mode=singletemplate", 'In page: Save template, Revert');
    add_row('C', 'teacher', 'Database: presets',
        "/mod/data/preset.php?d=$d", 'In page: Import, Export, Save as preset');
    add_row('C', 'teacher', 'Database: add a field',
        "/mod/data/field.php?d=$d&mode=new&newtype=text", 'In page: Save changes, Cancel');
} else {
    skip('Database screens', 'no data activity in this course');
}

if ($cm = first_cm('feedback')) {
    $id = $cm->id;
    add_row('C', 'student', 'Feedback: complete the form',
        "/mod/feedback/complete.php?id=$id", 'In page: Previous page, Next page, Submit your answers');
    add_row('C', 'teacher', 'Feedback: edit questions',
        "/mod/feedback/edit.php?id=$id&do_show=edit", 'In page: per item controls');
    add_row('C', 'teacher', 'Feedback: analysis',
        "/mod/feedback/analysis.php?id=$id", 'In page: Export to Excel');
    add_row('C', 'teacher', 'Feedback: non respondents',
        "/mod/feedback/show_nonrespondents.php?id=$id", 'In page: bulk send message');
    add_row('B', 'teacher', 'Feedback: responses list',
        "/mod/feedback/show_entries.php?id=$id",
        'Sticky footer holds "Go to all responses" only, prev / next response stay in page');
} else {
    skip('Feedback screens', 'no feedback activity in this course');
}

if ($cm = first_cm('book')) {
    $id = $cm->id;
    add_row('C', 'teacher', 'Book: edit a chapter',
        "/mod/book/edit.php?cmid=$id&pagenum=1", 'In page: Save changes, Cancel');

    $chapterid = $DB->get_field_sql(
        "SELECT id FROM {book_chapters} WHERE bookid = :bookid ORDER BY pagenum",
        ['bookid' => $cm->instance],
        IGNORE_MULTIPLE
    );
    if ($chapterid) {
        add_row('B', 'both', 'Book: a chapter (doubled navigation)',
            "/mod/book/view.php?id=$id&chapterid=$chapterid",
            'Sticky footer: Previous / Next ACTIVITY. In page: Previous / Next CHAPTER');
    } else {
        skip('Book chapter', 'this book has no chapters yet');
    }
} else {
    skip('Book screens', 'no book activity in this course');
}

if ($cm = first_cm('lesson')) {
    $id = $cm->id;
    add_row('C', 'teacher', 'Lesson: edit pages',
        "/mod/lesson/edit.php?id=$id", 'In page: per page action links');
    add_row('C', 'teacher', 'Lesson: report',
        "/mod/lesson/report.php?id=$id&action=detail", 'In page: bulk delete attempts');
    add_row('C', 'teacher', 'Lesson: grade essays',
        "/mod/lesson/essay.php?id=$id&mode=grade", 'In page: Save changes');
} else {
    skip('Lesson screens', 'no lesson activity in this course');
}

if ($cm = first_cm('workshop')) {
    $id = $cm->id;
    add_row('C', 'teacher', 'Workshop: edit assessment form',
        "/mod/workshop/editform.php?cmid=$id",
        'In page: Save and close, Save and preview, Save and continue');
    add_row('C', 'teacher', 'Workshop: allocate submissions',
        "/mod/workshop/allocation.php?cmid=$id&method=manual", 'In page: per row allocation controls');
    add_row('C', 'student', 'Workshop: submission',
        "/mod/workshop/submission.php?cmid=$id&edit=on", 'In page: Save changes, Cancel');

    $assessmentid = $DB->get_field_sql(
        "SELECT a.id
           FROM {workshop_assessments} a
           JOIN {workshop_submissions} s ON s.id = a.submissionid
          WHERE s.workshopid = :wid
       ORDER BY a.id DESC",
        ['wid' => $cm->instance],
        IGNORE_MULTIPLE
    );
    if ($assessmentid) {
        add_row('C', 'student', 'Workshop: assess a peer',
            "/mod/workshop/assessment.php?asid=$assessmentid",
            'In page: Save and close, Save and continue editing');
    } else {
        skip('Workshop: assess a peer', 'no assessments allocated yet');
    }
} else {
    skip('Workshop screens', 'no workshop activity in this course');
}

if ($cm = first_cm('wiki')) {
    $pageid = $DB->get_field_sql(
        "SELECT p.id
           FROM {wiki_pages} p
           JOIN {wiki_subwikis} sw ON sw.id = p.subwikiid
          WHERE sw.wikiid = :wikiid
       ORDER BY p.id",
        ['wikiid' => $cm->instance],
        IGNORE_MULTIPLE
    );
    if ($pageid) {
        add_row('C', 'student', 'Wiki: edit a page',
            "/mod/wiki/edit.php?pageid=$pageid", 'In page: Save, Preview, Cancel');
    } else {
        skip('Wiki: edit a page', 'this wiki has no pages yet, open it once to create the first page');
    }
} else {
    skip('Wiki screens', 'no wiki activity in this course');
}

if ($cm = first_cm('choice')) {
    add_row('C', 'teacher', 'Choice: responses',
        "/mod/choice/report.php?id={$cm->id}", 'In page: bulk action select, Go');
} else {
    skip('Choice screens', 'no choice activity in this course');
}

if ($cm = first_cm('scorm')) {
    add_row('C', 'student', 'SCORM: player',
        "/mod/scorm/player.php?a={$cm->instance}",
        'SCORM nav bar, position is a per activity setting: disabled, under content, or floating');
    add_row('C', 'teacher', 'SCORM: report',
        "/mod/scorm/report.php?id={$cm->id}&mode=basic", 'In page: bulk delete, Download');
} else {
    skip('SCORM screens', 'no scorm activity in this course');
}

if ($cm = first_cm('h5pactivity')) {
    add_row('B', 'teacher', 'H5P: attempts report',
        "/mod/h5pactivity/report.php?a={$cm->instance}",
        'Sticky footer holds "Go to all attempts" only for users without submit capability');
} else {
    skip('H5P screens', 'no h5pactivity in this course');
}

if ($cm = first_cm('folder')) {
    add_row('C', 'teacher', 'Folder: edit contents',
        "/mod/folder/edit.php?id={$cm->id}", 'In page: Save changes, Cancel');
} else {
    skip('Folder screens', 'no folder activity in this course');
}

if ($cm = first_cm('qbank')) {
    add_row('C', 'teacher', 'Question bank: questions',
        "/question/edit.php?cmid={$cm->id}", 'Bulk actions render into #sticky-footer via JS');
    add_row('C', 'teacher', 'Question bank: categories',
        "/question/bank/managecategories/category.php?cmid={$cm->id}", 'In page: Add category');
    add_row('C', 'teacher', 'Question bank: import',
        "/question/bank/importquestions/import.php?cmid={$cm->id}", 'In page: Import');
    add_row('C', 'teacher', 'Question bank: export',
        "/question/bank/exportquestions/export.php?cmid={$cm->id}", 'In page: Export questions to file');
} else {
    skip('Question bank screens', 'no qbank instance in this course');
}

// ---------------------------------------------------------------------------
// Filter and output.
// ---------------------------------------------------------------------------

$rows = array_values(array_filter($rows, function (array $row) use ($patternfilter, $rolefilter): bool {
    if ($patternfilter && !in_array($row['pattern'], $patternfilter, true)) {
        return false;
    }
    if ($rolefilter && $row['role'] !== $rolefilter && $row['role'] !== 'both') {
        return false;
    }
    return true;
}));

foreach ($rows as &$row) {
    $row['url'] = $base . $row['path'];
}
unset($row);

if ($options['json']) {
    cli_writeln(json_encode([
        'course' => [
            'id' => $course->id,
            'fullname' => format_string($course->fullname),
            'format' => $course->format,
            'contextid' => $coursecontext->id,
            'linearnavigation' => $linearnav,
            'usescourseindex' => $format->uses_course_index(),
        ],
        'wwwroot' => $base,
        'sampleusers' => ['student' => $studentid, 'teacher' => $teacherid],
        'screens' => $rows,
        'unresolved' => $skipped,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    exit(0);
}

$patternnames = [
    'A' => 'Bespoke core sticky footer',
    'B' => 'Linear navigation sticky footer',
    'C' => 'No footer, actions in page',
    'D' => 'Legacy in page activity navigation',
];

cli_heading(format_string($course->fullname) . " (id $course->id, format $course->format)");
cli_writeln('Linear navigation enabled: ' . ($linearnav ? 'yes' : 'no'));
cli_writeln('Screens resolved: ' . count($rows));
cli_writeln('');

foreach (['A', 'B', 'C', 'D'] as $pattern) {
    $group = array_values(array_filter($rows, fn(array $r): bool => $r['pattern'] === $pattern));
    if (!$group) {
        continue;
    }
    cli_heading("Pattern $pattern — {$patternnames[$pattern]} (" . count($group) . ')');
    foreach ($group as $row) {
        cli_writeln("  [{$row['role']}] {$row['screen']}");
        cli_writeln("      {$row['url']}");
        cli_writeln("      {$row['actions']}");
        cli_writeln('');
    }
}

if ($skipped) {
    cli_heading('Not resolvable in this course (' . count($skipped) . ')');
    foreach ($skipped as $note) {
        cli_writeln("  $note");
    }
}

exit(0);
