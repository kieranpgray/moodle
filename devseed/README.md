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
courses, or notifications. For those, see Option B.

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
| `seed_notifications.php` | Sends one notification per registered message provider to the test accounts |
| `fix_notifications.php` | Fills providers whose plugin is disabled (`message_send` drops those silently), guarantees popup rows, and re-spreads ages/read states |
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
- **User IDs are hardcoded.** `seed_notifications.php` targets user ids 2, 6, 8
  and 9 (admin, educator_busy, student_busy, bio_ava on the local instance).
  Those ids mean different people on any other site — repoint them first.
- **Course IDs are hardcoded.** The seeders assume Biology 101 is course 15.
- **They change site-wide settings.** `seed_course_content.php` sets
  `allowstealth = 1`; `seed_blocks.php` enables the `course_summary`, `feedback`,
  `rss_client` and `selfcompletion` block plugins.
- **`seed_course_content.php` deletes and rebuilds** any existing course with the
  shortname `DEPTH-DEMO`.
- They use `\core\test\phpunit\phpunit_util::get_data_generator()`, the testing
  data generator, driven from a live CLI. That works, but it is a testing API.

Prefer Option A for any site you care about.
