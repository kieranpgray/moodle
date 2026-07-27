#!/bin/sh
#
# devseed installer — puts the seeded test data onto a target Moodle site.
#
# Downloads the course backups and the notification seeder from GitHub,
# restores the courses, and seeds one notification per message provider.
#
# Usage:
#   sh install.sh --moodle=/var/www/html [options]
#
# Options:
#   --moodle=PATH         Moodle root (the dir containing config.php). Required.
#   --categoryid=N        Course category to restore into. Default: 1.
#   --users=LIST          Usernames for notifications. Default: admin.
#   --skip-courses        Don't restore the course backups.
#   --skip-notifications  Don't seed notifications.
#   --dry-run             Show what would happen; change nothing.
#   -h, --help            This help.
#
# The backups were taken from Moodle 5.3dev (2026072200). Moodle cannot restore
# a backup into an OLDER site, so this refuses to proceed if the target is
# behind that.
#
# POSIX sh — no bash required (Moodle containers are often Alpine).

set -eu

RAW="https://raw.githubusercontent.com/kieranpgray/moodle/devseed/biology-test-data/devseed"
BACKUP_VERSION=2026072200

MOODLE=""
CATEGORYID=1
USERS="admin"
SKIP_COURSES=0
SKIP_NOTIFS=0
DRYRUN=0

for arg in "$@"; do
  case "$arg" in
    --moodle=*)           MOODLE="${arg#*=}" ;;
    --categoryid=*)       CATEGORYID="${arg#*=}" ;;
    --users=*)            USERS="${arg#*=}" ;;
    --skip-courses)       SKIP_COURSES=1 ;;
    --skip-notifications) SKIP_NOTIFS=1 ;;
    --dry-run)            DRYRUN=1 ;;
    -h|--help)            sed -n '2,22p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Unknown option: $arg" >&2; exit 1 ;;
  esac
done

[ -n "$MOODLE" ] || { echo "ERROR: --moodle=PATH is required (dir containing config.php)." >&2; exit 1; }
[ -f "$MOODLE/config.php" ] || { echo "ERROR: no config.php found at $MOODLE" >&2; exit 1; }
command -v php >/dev/null 2>&1 || { echo "ERROR: php not on PATH" >&2; exit 1; }

# Download helper: curl or wget, whichever exists.
fetch() { # fetch <url> <dest>
  if command -v curl >/dev/null 2>&1; then
    curl -fsSL "$1" -o "$2"
  elif command -v wget >/dev/null 2>&1; then
    wget -qO "$2" "$1"
  else
    echo "ERROR: neither curl nor wget is available" >&2; exit 1
  fi
}

echo "Target Moodle : $MOODLE"
echo "Category id   : $CATEGORYID"
echo "Notify users  : $USERS"
[ "$DRYRUN" -eq 1 ] && echo "MODE          : DRY RUN (nothing will be written)"
echo

# --- Preflight: version compatibility ---------------------------------------
echo "== Preflight =="
TARGET_VERSION=$(php -r 'define("CLI_SCRIPT",true); require($argv[1]."/config.php"); echo (int)$CFG->version;' "$MOODLE" 2>/dev/null || echo 0)
TARGET_RELEASE=$(php -r 'define("CLI_SCRIPT",true); require($argv[1]."/config.php"); echo $CFG->release;' "$MOODLE" 2>/dev/null || echo unknown)

echo "  Target version : $TARGET_VERSION ($TARGET_RELEASE)"
echo "  Backup version : $BACKUP_VERSION (5.3dev Build 20260722)"

if [ "$TARGET_VERSION" -eq 0 ]; then
  echo "  WARNING: could not read the target version; the restore may fail."
elif [ "$TARGET_VERSION" -lt "$BACKUP_VERSION" ]; then
  echo ""
  echo "  ERROR: the target site is OLDER than the backup." >&2
  echo "  Moodle cannot restore a backup taken on a newer version." >&2
  echo "  Upgrade the target to at least $BACKUP_VERSION, or re-take the" >&2
  echo "  backups on a site matching the target (scripts/backup_nousers.php)." >&2
  exit 1
else
  echo "  OK: target is new enough."
fi
echo

TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT INT TERM

# --- Courses -----------------------------------------------------------------
if [ "$SKIP_COURSES" -eq 0 ]; then
  echo "== Restoring courses =="
  for f in backup-course-15-MYOV-T-02-nousers.mbz backup-course-78-DEPTH-DEMO-nousers.mbz; do
    if [ "$DRYRUN" -eq 1 ]; then
      echo "  [dry run] would download $f and restore into category $CATEGORYID"
      continue
    fi
    echo "  downloading $f"
    fetch "$RAW/backups/$f" "$TMP/$f"
    echo "  restoring into category $CATEGORYID ..."
    php "$MOODLE/admin/cli/restore_backup.php" --file="$TMP/$f" --categoryid="$CATEGORYID"
  done
  echo
fi

# --- Notifications -----------------------------------------------------------
if [ "$SKIP_NOTIFS" -eq 0 ]; then
  echo "== Seeding notifications =="
  fetch "$RAW/scripts/seed_notifications.php" "$TMP/seed_notifications.php"
  if [ "$DRYRUN" -eq 1 ]; then
    php "$TMP/seed_notifications.php" --config="$MOODLE/config.php" --users="$USERS" --dry-run
  else
    php "$TMP/seed_notifications.php" --config="$MOODLE/config.php" --users="$USERS"
  fi
  echo
fi

echo "== Done =="
echo "If the site looks stale, purge caches:"
echo "  php $MOODLE/admin/cli/purge_caches.php"
