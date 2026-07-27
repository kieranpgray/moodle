<?php
/**
 * Portable notification seeder.
 *
 * Emits one notification for every registered message provider to each named
 * recipient, with a spread of ages and read states, so every notification type
 * can be reviewed in the UI.
 *
 * Portable across sites: recipients are resolved by USERNAME (not by the numeric
 * ids of whatever site it was written on), and the Moodle root is auto-detected.
 *
 * Usage:
 *   php seed_notifications.php --users=admin,teacher1,student1
 *   php seed_notifications.php --users=admin --dry-run
 *   php seed_notifications.php --reset
 *
 * Options:
 *   --users=LIST   Comma-separated usernames (or numeric ids). Defaults to all
 *                  site admins.
 *   --reset        Remove notifications previously created by this script, then exit.
 *   --dry-run      Report what would happen without writing.
 *   --config=PATH  Explicit path to Moodle's config.php.
 *   -h, --help     This help.
 */

define('CLI_SCRIPT', true);

// ---------------------------------------------------------------------------
// Parse args before bootstrapping (we may need --config to find Moodle at all).
// ---------------------------------------------------------------------------
$args = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z0-9-]+)(?:=(.*))?$/i', $a, $m)) {
        $args[$m[1]] = $m[2] ?? true;
    } else if ($a === '-h') {
        $args['help'] = true;
    }
}

if (!empty($args['help'])) {
    $doc = file_get_contents(__FILE__);
    preg_match('~/\*\*(.*?)\*/~s', $doc, $m);
    echo preg_replace('/^\s*\*ss?/m', '', $m[1] ?? 'No help available.') . "\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Locate config.php. This script normally lives at devseed/scripts/, so the
// Moodle root is two levels up; fall back to the split-docroot shim and to
// walking up from the current directory.
// ---------------------------------------------------------------------------
$candidates = [];
if (!empty($args['config']) && is_string($args['config'])) {
    $candidates[] = $args['config'];
}
$candidates[] = __DIR__ . '/../../config.php';
$candidates[] = __DIR__ . '/../../public/config.php';
$dir = getcwd();
for ($i = 0; $i < 6; $i++) {
    $candidates[] = $dir . '/config.php';
    $candidates[] = $dir . '/public/config.php';
    $dir = dirname($dir);
}

$configpath = null;
foreach ($candidates as $c) {
    if ($c && file_exists($c)) {
        $configpath = realpath($c);
        break;
    }
}
if (!$configpath) {
    fwrite(STDERR, "Could not find Moodle's config.php. Pass --config=/path/to/config.php\n");
    exit(1);
}

require($configpath);
require_once($CFG->dirroot . '/lib/messagelib.php');

global $DB, $CFG;

echo "Moodle root: {$CFG->dirroot}\n";
echo "Site:        {$CFG->wwwroot}\n";

const SEED_MARKER = 'devseed-notifications';

$dryrun = !empty($args['dry-run']);

// ---------------------------------------------------------------------------
// Reset
// ---------------------------------------------------------------------------
if (!empty($args['reset'])) {
    $ids = $DB->get_fieldset_select('notifications', 'id',
        $DB->sql_like('customdata', '?'), ['%' . SEED_MARKER . '%']);
    if ($ids) {
        list($insql, $inparams) = $DB->get_in_or_equal($ids);
        $DB->delete_records_select('message_popup_notifications', "notificationid $insql", $inparams);
        $DB->delete_records_select('notifications', "id $insql", $inparams);
    }
    echo 'Removed ' . count($ids) . " seeded notification(s).\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Resolve recipients by username (portable) or numeric id.
// ---------------------------------------------------------------------------
function resolve_recipients(string $spec): array {
    global $DB, $CFG;

    if (trim($spec) === '') {
        $ids = array_filter(array_map('intval', explode(',', (string) ($CFG->siteadmins ?? ''))));
        if (!$ids) {
            return [];
        }
        $users = $DB->get_records_list('user', 'id', $ids);
        echo "No --users given; defaulting to site admins.\n";
        return $users;
    }

    $out = [];
    foreach (explode(',', $spec) as $token) {
        $token = trim($token);
        if ($token === '') {
            continue;
        }
        $user = ctype_digit($token)
            ? $DB->get_record('user', ['id' => (int) $token])
            : $DB->get_record('user', ['username' => $token]);

        if (!$user) {
            echo "  WARNING: no such user '{$token}' — skipping.\n";
            continue;
        }
        if ($user->deleted || $user->username === 'guest') {
            echo "  WARNING: user '{$token}' is deleted or guest — skipping.\n";
            continue;
        }
        $out[$user->id] = $user;
    }
    return $out;
}

$recipients = resolve_recipients(is_string($args['users'] ?? '') ? $args['users'] : '');

if (!$recipients) {
    fwrite(STDERR, "No valid recipients resolved. Pass --users=username1,username2\n");
    exit(1);
}

echo 'Recipients (' . count($recipients) . '): '
    . implode(', ', array_map(fn($u) => $u->username, $recipients)) . "\n";

$providers = $DB->get_records('message_providers', null, 'component ASC, name ASC');
echo 'Registered message providers: ' . count($providers) . "\n";

if ($dryrun) {
    echo 'DRY RUN — would create ' . (count($providers) * count($recipients))
        . " notification(s).\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Seed
// ---------------------------------------------------------------------------
$noreply = core_user::get_noreply_user();
$now = time();
$ages = [2 * MINSECS, 45 * MINSECS, 5 * HOURSECS, 26 * HOURSECS,
         3 * DAYSECS, 9 * DAYSECS, 40 * DAYSECS];

$viaapi = 0;
$viadirect = 0;
$i = 0;

foreach ($providers as $provider) {
    $label = $provider->component . ' / ' . $provider->name;

    foreach ($recipients as $user) {
        $i++;
        $timecreated = $now - $ages[$i % count($ages)];
        $read = ($i % 3 === 0);
        $customdata = ['seed' => SEED_MARKER, 'provider' => $label];

        $subject = "[{$label}] Sample notification";
        $plain = "Test notification for provider: {$label}.\n\n"
            . "Seeded for UI review. Recipient: {$user->username}.";
        $html = '<p>Test notification for provider <strong>' . s($label) . '</strong>.</p>'
            . '<p>Seeded for UI review of notification rendering.</p>';

        $id = null;

        // Preferred path: the real messaging API.
        try {
            $message = new \core\message\message();
            $message->component = $provider->component;
            $message->name = $provider->name;
            $message->userfrom = $noreply;
            $message->userto = $user;
            $message->subject = $subject;
            $message->fullmessage = $plain;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = $html;
            $message->smallmessage = 'Sample notification from ' . $label;
            $message->notification = 1;
            $message->courseid = SITEID;
            $message->contexturl = (new moodle_url('/message/output/popup/notifications.php'))->out(false);
            $message->contexturlname = 'All notifications';
            $message->customdata = (object) $customdata;

            $id = message_send($message);
            if ($id) {
                $viaapi++;
            }
        } catch (Throwable $e) {
            $id = null;
        }

        // message_send silently drops providers whose plugin is disabled, and
        // does not always return an id even when it did insert. Reconcile.
        if (!$id) {
            $existing = $DB->get_record_select('notifications',
                'useridto = ? AND component = ? AND eventtype = ? AND '
                . $DB->sql_like('customdata', '?'),
                [$user->id, $provider->component, $provider->name, '%' . SEED_MARKER . '%'],
                'id', IGNORE_MULTIPLE);

            if ($existing) {
                $id = $existing->id;
                $viaapi++;
            } else {
                $id = $DB->insert_record('notifications', (object) [
                    'useridfrom' => $noreply->id,
                    'useridto' => $user->id,
                    'subject' => $subject,
                    'fullmessage' => $plain,
                    'fullmessageformat' => FORMAT_PLAIN,
                    'fullmessagehtml' => $html,
                    'smallmessage' => 'Sample notification from ' . $label,
                    'component' => $provider->component,
                    'eventtype' => $provider->name,
                    'contexturl' => (new moodle_url('/message/output/popup/notifications.php'))->out(false),
                    'contexturlname' => 'All notifications',
                    'timeread' => null,
                    'timecreated' => $timecreated,
                    'customdata' => json_encode($customdata),
                ]);
                $viadirect++;
            }
        }

        // Apply the age spread and read state.
        $update = (object) ['id' => $id, 'timecreated' => $timecreated, 'timeread' => null];
        if ($read) {
            $update->timeread = $timecreated + (10 * MINSECS);
        }
        $DB->update_record('notifications', $update);

        // Guarantee it appears in the bell menu.
        if (!$DB->record_exists('message_popup_notifications', ['notificationid' => $id])) {
            $DB->insert_record('message_popup_notifications', (object) ['notificationid' => $id]);
        }
    }
}

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------
echo "\nCreated via messaging API: {$viaapi}\n";
echo "Created by direct insert (disabled plugins): {$viadirect}\n";

$covered = $DB->get_field_sql(
    "SELECT COUNT(DISTINCT component || '-' || eventtype) FROM {notifications}");
echo "Distinct provider pairs present: {$covered} of " . count($providers) . "\n";

foreach ($recipients as $u) {
    $c = $DB->count_records('notifications', ['useridto' => $u->id]);
    $unread = $DB->count_records_select('notifications',
        'useridto = ? AND timeread IS NULL', [$u->id]);
    echo "  {$u->username}: {$c} notifications ({$unread} unread)\n";
}

$missing = $DB->get_records_sql("
    SELECT p.component, p.name
      FROM {message_providers} p
 LEFT JOIN (SELECT DISTINCT component, eventtype FROM {notifications}) n
        ON n.component = p.component AND n.eventtype = p.name
     WHERE n.component IS NULL");
if ($missing) {
    echo "\nProviders with no notification (" . count($missing) . "):\n";
    foreach ($missing as $m) {
        echo "  - {$m->component} / {$m->name}\n";
    }
} else {
    echo "\nAll registered providers are represented.\n";
}
