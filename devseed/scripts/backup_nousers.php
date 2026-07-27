<?php
/**
 * Course backup with user data EXCLUDED, but blocks/activities retained.
 * The stock admin/cli/backup.php always includes users; this does not.
 *
 * Usage: php backup_nousers.php <courseid> <destination-dir>
 */

define('CLI_SCRIPT', true);
require('/var/www/html/public/config.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

global $DB, $CFG;

\core\cron::setup_user(get_admin());

$courseid = (int) $argv[1];
$destdir = rtrim($argv[2] ?? '/tmp/backups', '/');
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

mtrace("Backing up course {$courseid} ({$course->shortname}) without user data...");

$bc = new backup_controller(
    backup::TYPE_1COURSE, $courseid, backup::FORMAT_MOODLE,
    backup::INTERACTIVE_NO, backup::MODE_GENERAL, get_admin()->id
);

$plan = $bc->get_plan();

// Turn off everything that carries user-identifying data; keep structure + blocks.
$off = ['users', 'anonymize', 'role_assignments', 'userscompletion',
        'logs', 'grade_histories', 'comments', 'badges', 'groups'];
foreach ($off as $name) {
    try {
        $setting = $plan->get_setting($name);
        $setting->set_status(base_setting::NOT_LOCKED);
        $setting->set_value(false);
    } catch (Throwable $e) {
        mtrace("  (setting '{$name}' not applicable: " . $e->getMessage() . ')');
    }
}

// Explicitly keep the things we DO want.
foreach (['activities', 'blocks', 'filters', 'questionbank', 'calendarevents', 'customfield'] as $name) {
    try {
        $setting = $plan->get_setting($name);
        $setting->set_status(base_setting::NOT_LOCKED);
        $setting->set_value(true);
    } catch (Throwable $e) {
        mtrace("  (setting '{$name}' not applicable)");
    }
}

$bc->set_status(backup::STATUS_AWAITING);
$bc->execute_plan();
$results = $bc->get_results();
$file = $results['backup_destination'];

if (!$file) {
    cli_error('Backup produced no file.');
}

if (!is_dir($destdir)) {
    mkdir($destdir, 0777, true);
}
$filename = "backup-course-{$courseid}-{$course->shortname}-nousers.mbz";
$path = "{$destdir}/{$filename}";
$file->copy_content_to($path);
$file->delete();
$bc->destroy();

mtrace("Wrote {$path} (" . round(filesize($path) / 1024) . " KB)");
