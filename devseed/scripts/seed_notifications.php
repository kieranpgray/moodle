<?php
/**
 * Dev seeder: emit one notification for every registered message provider,
 * to each of the primary test accounts, with a spread of ages and read states.
 *
 * Run:  php /tmp/seed_notifications.php [--reset]
 */

define('CLI_SCRIPT', true);
require('/var/www/html/public/config.php');
require_once($CFG->dirroot . '/lib/messagelib.php');

global $DB, $CFG;

$reset = in_array('--reset', $argv, true);

// Marker so seeded rows can be identified and cleaned up later.
const SEED_MARKER = 'depthdemo-seed';

if ($reset) {
    $ids = $DB->get_fieldset_select('notifications', 'id', $DB->sql_like('customdata', '?'), ['%' . SEED_MARKER . '%']);
    if ($ids) {
        list($insql, $inparams) = $DB->get_in_or_equal($ids);
        $DB->delete_records_select('message_popup_notifications', "notificationid $insql", $inparams);
        $DB->delete_records_select('notifications', "id $insql", $inparams);
    }
    mtrace('Reset: removed ' . count($ids) . ' previously seeded notifications.');
}

// Recipients: admin, busy teacher, busy student, one Biology cohort student.
$recipientids = [2, 6, 8, 9];
$recipients = $DB->get_records_list('user', 'id', $recipientids);
if (count($recipients) !== count($recipientids)) {
    mtrace('WARNING: not all expected recipient users exist.');
}

$noreply = core_user::get_noreply_user();

$providers = $DB->get_records('message_providers', null, 'component ASC, name ASC');
mtrace('Found ' . count($providers) . ' registered message providers.');

$now = time();
// Ages chosen to exercise "just now / today / yesterday / this week / older" grouping.
$ages = [
    2 * MINSECS,
    45 * MINSECS,
    5 * HOURSECS,
    26 * HOURSECS,
    3 * DAYSECS,
    9 * DAYSECS,
    40 * DAYSECS,
];

$sent = 0;
$failed = [];
$i = 0;

foreach ($providers as $provider) {
    foreach ($recipients as $user) {
        $i++;
        $age = $ages[$i % count($ages)];
        $timecreated = $now - $age;
        // Roughly one in three marked read, so both states are visible in the UI.
        $read = ($i % 3 === 0);

        $label = $provider->component . ' / ' . $provider->name;

        $message = new \core\message\message();
        $message->component = $provider->component;
        $message->name = $provider->name;
        $message->userfrom = $noreply;
        $message->userto = $user;
        $message->subject = '[' . $label . '] Sample notification';
        $message->fullmessage = "Test notification for provider: {$label}.\n\n"
            . "Seeded for UI review of notification rendering. Recipient: {$user->username}.";
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '<p>Test notification for provider <strong>' . s($label) . '</strong>.</p>'
            . '<p>Seeded for UI review of notification rendering. Recipient: <em>' . s($user->username) . '</em>.</p>';
        $message->smallmessage = 'Sample notification from ' . $label;
        $message->notification = 1;
        $message->courseid = SITEID;
        $message->contexturl = (new moodle_url('/message/output/popup/notifications.php'))->out(false);
        $message->contexturlname = 'All notifications';
        $message->customdata = (object) ['seed' => SEED_MARKER, 'provider' => $label];

        try {
            $id = message_send($message);
        } catch (Throwable $e) {
            $failed[] = $label . ' -> ' . $user->username . ': ' . $e->getMessage();
            continue;
        }

        if (!$id) {
            $failed[] = $label . ' -> ' . $user->username . ': message_send returned falsy';
            continue;
        }

        // message_send stamps "now"; rewrite so the list shows a realistic spread,
        // and set the read state.
        $update = (object) ['id' => $id, 'timecreated' => $timecreated];
        if ($read) {
            $update->timeread = $timecreated + (10 * MINSECS);
        }
        $DB->update_record('notifications', $update);

        // Guarantee it shows in the bell menu even if the popup processor was skipped.
        if (!$DB->record_exists('message_popup_notifications', ['notificationid' => $id])) {
            $DB->insert_record('message_popup_notifications', (object) ['notificationid' => $id]);
        }

        $sent++;
    }
}

mtrace("Seeded {$sent} notifications across " . count($recipients) . ' users.');
if ($failed) {
    mtrace('Failures (' . count($failed) . '):');
    foreach (array_slice($failed, 0, 20) as $f) {
        mtrace('  - ' . $f);
    }
}

// Report coverage.
$covered = $DB->get_field_sql(
    'SELECT COUNT(DISTINCT component || \'/\' || eventtype) FROM {notifications}'
);
mtrace("Distinct component/eventtype pairs now present in notifications: {$covered}");
mtrace('Total notifications: ' . $DB->count_records('notifications'));
