<?php
/** Audit which activity date fields are actually populated in a course. */

define('CLI_SCRIPT', true);
require('/var/www/html/public/config.php');
global $DB;

$courseid = (int) ($argv[1] ?? 15);

// Genuine date fields only (flags/bools excluded).
$datefields = [
    'assign'    => ['allowsubmissionsfromdate', 'duedate', 'cutoffdate', 'gradingduedate', 'timelimit'],
    'quiz'      => ['timeopen', 'timeclose', 'timelimit'],
    'choice'    => ['timeopen', 'timeclose'],
    'feedback'  => ['timeopen', 'timeclose'],
    'lesson'    => ['available', 'deadline', 'timelimit'],
    'data'      => ['timeavailablefrom', 'timeavailableto', 'timeviewfrom', 'timeviewto', 'assesstimestart', 'assesstimefinish'],
    'forum'     => ['duedate', 'cutoffdate', 'assesstimestart', 'assesstimefinish'],
    'glossary'  => ['assesstimestart', 'assesstimefinish'],
    'workshop'  => ['submissionstart', 'submissionend', 'assessmentstart', 'assessmentend'],
    'scorm'     => ['timeopen', 'timeclose'],
    'wiki'      => ['editend'],
];

echo "Date-field coverage for course {$courseid}\n";
echo str_repeat('=', 78) . "\n";

$totalfields = 0;
$coveredfields = 0;

foreach ($datefields as $mod => $fields) {
    if (!$DB->get_manager()->table_exists($mod)) {
        continue;
    }
    $instances = $DB->get_records($mod, ['course' => $courseid]);
    $count = count($instances);
    printf("%-10s %2d instance(s)\n", $mod, $count);
    foreach ($fields as $f) {
        $totalfields++;
        $set = 0;
        foreach ($instances as $inst) {
            if (!empty($inst->$f)) {
                $set++;
            }
        }
        if ($set > 0) {
            $coveredfields++;
        }
        printf("    %-26s set on %2d / %-2d  %s\n", $f, $set, $count, $set ? 'OK' : '<-- GAP');
    }
}

// completionexpected applies to every module.
$totalfields++;
$ce = $DB->count_records_select('course_modules', 'course = ? AND completionexpected > 0', [$courseid]);
if ($ce) {
    $coveredfields++;
}
printf("\n%-10s %s\n", 'all mods', "completionexpected set on {$ce} course module(s) " . ($ce ? 'OK' : '<-- GAP'));

echo str_repeat('=', 78) . "\n";
printf("Coverage: %d / %d date fields have at least one value set.\n", $coveredfields, $totalfields);

// Temporal spread of the dates that ARE set.
echo "\nTemporal spread of populated dates (assign/quiz only, as a sample):\n";
$now = time();
foreach (['assign' => ['duedate', 'allowsubmissionsfromdate', 'cutoffdate', 'gradingduedate'],
          'quiz' => ['timeopen', 'timeclose']] as $mod => $fields) {
    foreach ($DB->get_records($mod, ['course' => $courseid]) as $inst) {
        foreach ($fields as $f) {
            if (empty($inst->$f)) {
                continue;
            }
            $delta = $inst->$f - $now;
            $when = $delta < 0 ? floor(-$delta / DAYSECS) . 'd ago' : 'in ' . floor($delta / DAYSECS) . 'd';
            printf("  %-8s %-40s %-26s %s\n", $mod, substr($inst->name, 0, 38), $f, $when);
        }
    }
}
