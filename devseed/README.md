# devseed — portable test data for UI review

Test data built on a local Moodle 5.3dev instance for reviewing the MDL-87805
depth/elevation work, notification rendering, subsections and activity date states.

**Why this directory exists:** Moodle stores courses, users, blocks and
notifications in the *database* and in `moodledata` — none of it lives in the git
repository. Deploying code from git therefore never carries test data with it.
This directory holds the two artefacts that *can* travel through git and rebuild
that data on another site.

---

## What's here

```
devseed/
  backups/   Moodle course backups (.mbz) — restore these to get the courses
  scripts/   CLI seeders — regenerate site-level data and refresh dates
```

---

## Option A — restore the course backups (recommended)

This is the safe, portable route. It needs no shell access and hardcodes no IDs.

| File | Course | Size |
|---|---|---|
| `backup-course-15-MYOV-T-02-nousers.mbz` | Biology 101 — Mock data | 1.5 MB |
| `backup-course-78-DEPTH-DEMO-nousers.mbz` | Surface & depth showcase | 16 KB |

**These backups deliberately contain no user data.** Moodle's stock
`admin/cli/backup.php` always embeds user records — usernames, email addresses
and last-login IPs — which have no place in a public repository and would create
colliding accounts (such as `admin@moodle.local`) when restored onto a real site.
These were produced with `scripts/backup_nousers.php`, which disables the
`users`, `role_assignments`, `logs`, `grade_histories`, `comments`, `badges` and
`groups` settings while keeping activities and blocks.

The practical consequence: after restoring, enrol your own users. There are no
submissions, grades or completion records, because those belong to users.

**Via the web UI:** Site administration → Courses → Restore course, upload the
`.mbz`, and restore as a new course.

**Via CLI:**

```bash
php admin/cli/restore_backup.php --file=/path/to/backup.mbz --categoryid=1
```

A course backup carries sections, subsections (delegated sections), all
activities with their full date configuration, section/activity visibility and
access restrictions, and course-level block instances.

It does **not** carry site-level data: user accounts, enrolments to other
courses, or notifications. For notifications, see the next section.

---

## Notifications

Notifications are site-level, not course-level, so no course backup can carry
them. Rebuild them on the target instead:

```bash
php devseed/scripts/seed_notifications.php --users=admin,someteacher,somestudent
```

This sends one notification for **every registered message provider** to each
named user, with a spread of ages (2 minutes to 40 days) and roughly a third
marked read, so the full range of notification rendering can be reviewed.

It is safe to point at any site:

- **Recipients are resolved by username**, not by numeric id, so it does not
  matter that the ids differ from the machine it was written on. Unknown users
  are reported and skipped. With no `--users`, it defaults to the site admins.
- **Providers are read from the database**, so it covers whatever is installed
  on that site rather than a hardcoded list.
- **Moodle's root is auto-detected** relative to the script, with `--config=PATH`
  as an override.

Useful flags: `--dry-run` reports what would be created without writing;
`--reset` removes everything this script previously created.

Note that `message_send()` silently drops providers whose plugin is disabled
(on a stock site that's typically the unused enrolment plugins and
BigBlueButton). The script detects those and inserts them directly, so coverage
is complete either way — the summary reports the split.

### What Biology 101 contains

- 21 sections including 5 subsections (populated, empty, hidden, and one holding
  restricted activities)
- A **"Date matrix"** section exercising all 35 activity date fields across every
  temporal state — not-yet-open, open, due tomorrow, overdue-but-accepting, and
  closed-past-cutoff — with a `MAXIMUM` variant per module type that sets every
  date field at once
- A block drawer populated with 26 blocks

### What the showcase course contains

Every section and activity state that renders with a distinct surface treatment
under the new depth model: highlighted, hidden, date-restricted and empty
sections; populated/empty/hidden subsections; and a five-way activity visibility
matrix (normal, hidden, stealth, restricted-with-info, restricted-hidden).

---

## Option B — run the seeders

Use these to regenerate site-level data, or to refresh dates once they drift.

```bash
docker exec <php-container> php /path/to/devseed/scripts/<script>.php
```

| Script | Purpose |
|---|---|
| `seed_notifications.php` | One notification per registered message provider, to users named by `--users`. **Portable — safe on any site.** See above |
| `seed_course_content.php` | Adds subsection variations to Biology 101 and builds the showcase course |
| `seed_resource.php` | Adds the file resource that renders the `badge-none` type badge (split out — its generator needs a current user) |
| `seed_date_matrix.php` | Builds the complete activity date matrix |
| `seed_blocks.php` | Populates a course's block drawer (`--apply` to write; dry run by default) |
| `audit_dates.php` | Reports which activity date fields are populated, and their temporal spread |
| `backup_nousers.php` | Regenerates the `.mbz` files above with user data excluded (`<courseid> <destdir>`) |

### `seed_date_matrix.php` is the one to re-run

Every other seeder writes fixed timestamps, so their labels drift out of sync as
real time passes — an activity labelled "due tomorrow" is simply wrong a week
later. `seed_date_matrix.php` is idempotent: it wipes and rebuilds its own
section with every date recomputed relative to *now*.

```bash
php devseed/scripts/seed_date_matrix.php 15    # 15 = target course id
```

---

## Read this before running the scripts anywhere real

These are **development seeders**. They are not safe on a site with real users.

- **Paths are hardcoded** to `/var/www/html/public/config.php`. Moodle 5.x uses a
  split docroot — most code sits under `/public`, but CLI scripts stay at the
  repo root. Adjust for the target.
- **`seed_notifications.php` is the exception — it is portable.** It resolves
  users by username and auto-detects the Moodle root, so it is safe to run
  anywhere. Everything below applies to the *other* scripts.
- **Course IDs are hardcoded.** The seeders assume Biology 101 is course 15.
- **They change site-wide settings.** `seed_course_content.php` sets
  `allowstealth = 1`; `seed_blocks.php` enables the `course_summary`, `feedback`,
  `rss_client` and `selfcompletion` block plugins.
- **`seed_course_content.php` deletes and rebuilds** any existing course with the
  shortname `DEPTH-DEMO`.
- They use `\core\test\phpunit\phpunit_util::get_data_generator()`, the testing
  data generator, driven from a live CLI. That works, but it is a testing API.

Prefer Option A for any site you care about.
