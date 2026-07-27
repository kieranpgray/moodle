<?php
/**
 * Fix-up pass for the notification seed:
 *  1. Directly insert notifications for providers whose plugin is disabled
 *     (message_send drops those silently).
 *  2. Ensure every seeded notification has a popup row so it shows in the bell.
 *  3. Re-apply a spread of ages and read states across all seeded rows.
 */

define('CLI_SCRIPT', true);
require('/var/www/html/public/config.php');

global $DB;

const SEED_MARKER = 'depthdemo-seed';

$recipientids = [2, 6, 8, 9];
$recipients = $DB->get_records_list('user', 'id', $recipientids);
$noreplyid = core_user::get_noreply_user()->id;
$now = time();

// 1. Providers that produced nothing (disabled plugins) -> direct insert.
$missing = $DB->get_records_sql("
    SELECT p.id, p.component, p.name
      FROM {message_providers} p
 LEFT JOIN (SELECT DISTINCT component, eventtype FROM {notifications}) n
        ON n.component = p.component AND n.eventtype = p.name
     WHERE n.component IS NULL
  ORDER BY p.component, p.name");

mtrace('Providers still missing: ' . count($missing));

$inserted = 0;
foreach ($missing as $provider) {
    $label = $provider->component . ' / ' . $provider->name;
    foreach ($recipients as $user) {
        $rec = (object) [
            'useridfrom' => $noreplyid,
            'useridto' => $user->id,
            'subject' => '[' . $label . '] Sample notification',
            'fullmessage' => "Test notification for provider: {$label}.\n\n"
                . "Seeded directly (provider's plugin is disabled). Recipient: {$user->username}.",
            'fullmessageformat' => FORMAT_PLAIN,
            'fullmessagehtml' => '<p>Test notification for provider <strong>' . s($label) . '</strong>.</p>'
                . '<p>Seeded directly (this provider\'s plugin is disabled).</p>',
            'smallmessage' => 'Sample notification from ' . $label,
            'component' => $provider->component,
            'eventtype' => $provider->name,
            'contexturl' => (new moodle_url('/message/output/popup/notifications.php'))->out(false),
            'contexturlname' => 'All notifications',
            'timeread' => null,
            'timecreated' => $now - (3 * HOURSECS),
            'customdata' => json_encode(['seed' => SEED_MARKER, 'provider' => $label]),
        ];
        $id = $DB->insert_record('notifications', $rec);
        $DB->insert_record('message_popup_notifications', (object) ['notificationid' => $id]);
        $inserted++;
    }
}
mtrace("Directly inserted {$inserted} notifications for disabled-plugin providers.");

// 2. Backfill any seeded notification lacking a popup row.
$nopopup = $DB->get_fieldset_sql("
    SELECT n.id FROM {notifications} n
 LEFT JOIN {message_popup_notifications} p ON p.notificationid = n.id
     WHERE " . $DB->sql_like('n.customdata', '?') . " AND p.id IS NULL", ['%' . SEED_MARKER . '%']);
foreach ($nopopup as $nid) {
    $DB->insert_record('message_popup_notifications', (object) ['notificationid' => $nid]);
}
mtrace('Backfilled popup rows: ' . count($nopopup));

// 3. Re-spread ages and read states over every seeded row.
$ages = [
    2 * MINSECS, 45 * MINSECS, 5 * HOURSECS, 26 * HOURSECS,
    3 * DAYSECS, 9 * DAYSECS, 40 * DAYSECS,
];
$seeded = $DB->get_records_select('notifications', $DB->sql_like('customdata', '?'),
    ['%' . SEED_MARKER . '%'], 'id ASC', 'id');

$i = 0;
$readcount = 0;
foreach ($seeded as $row) {
    $i++;
    $timecreated = $now - $ages[$i % count($ages)];
    $update = (object) ['id' => $row->id, 'timecreated' => $timecreated, 'timeread' => null];
    if ($i % 3 === 0) {
        $update->timeread = $timecreated + (10 * MINSECS);
        $readcount++;
    }
    $DB->update_record('notifications', $update);
}
mtrace('Re-spread ' . count($seeded) . " seeded notifications ({$readcount} marked read).");

// Report.
$total = $DB->count_records('notifications');
$pairs = $DB->get_field_sql("SELECT COUNT(DISTINCT component || '/' || eventtype) FROM {notifications}");
$providers = $DB->count_records('message_providers');
mtrace("Coverage: {$pairs} distinct provider pairs present of {$providers} registered. Total notifications: {$total}");

foreach ($recipients as $u) {
    $c = $DB->count_records('notifications', ['useridto' => $u->id]);
    $unread = $DB->count_records_select('notifications', 'useridto = ? AND timeread IS NULL', [$u->id]);
    mtrace("  {$u->username} (id {$u->id}): {$c} notifications, {$unread} unread");
}
