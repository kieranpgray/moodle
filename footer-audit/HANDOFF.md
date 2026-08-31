# Sticky footer / bottom navigation UX audit — handoff

Scratch working directory for a UX audit of Moodle's bottom action zone. Started in a
Claude Code cloud session, which could not reach the local Moodle instance. This note
exists so a local session can pick it up with full context.

## The question being answered

Across every screen an educator or a student can reach inside a course activity, what
sits in the bottom action zone — sticky footer, bottom navigation, save buttons, other
key actions? The objective is to see how much divergence there is in controls that
arguably should share one consistent sticky footer pattern.

## The instance

| | |
|---|---|
| Base URL | `https://localhost:9443` |
| Course under audit | Biology 101, `id=15` |
| Learner account | `student_light` (3 courses) or `student_busy` (30 courses) |
| Educator account | `educator_busy` (30 courses) — plain `educator` has 0 courses |
| Site admin | `admin` |

Passwords are in `.claude.env` on the local machine. `educator` and `student` exist but
are enrolled in nothing, so they are poor choices for the gradebook screens.

## What already exists

**`admin/cli/footer_audit_urls.php`** — resolves the audit URLs for a course against the
database and prints them grouped by pattern, with the actions in each screen's footer
zone. Screens the course cannot currently produce (a quiz with no attempts, a wiki with
no pages) are reported with the reason rather than dropped.

```
php admin/cli/footer_audit_urls.php --courseid=15 --wwwroot=https://localhost:9443 \
  --student=student_light --teacher=educator_busy
```

Add `--json` for machine-readable output, `--pattern=C --role=student` to narrow it.

**This script has never been executed.** It was written and linted without a database.
Running it is the first thing to do locally; expect to fix something.

**`footer-audit/audit-page.html`** — source of the published audit page:
https://claude.ai/code/artifact/438946dc-3545-472e-abd1-e76b00ca94be

To update that page rather than creating a second one, publish with its URL passed as
`url`. It has a panel that accepts the script's `--json` output and rewrites every
`{placeholder}` in the inventory with real IDs.

## The four patterns

The finding is that four independent mechanisms compete for the same zone.

| | Mechanism | Where |
|---|---|---|
| **A** | Page builds its own `core\output\sticky_footer` and fills it with anything | 16 screens |
| **B** | Linear navigation footer, auto-injected by a hook, fixed content contract | activity pages |
| **C** | `set_show_navigation_footer(false)`, actions rendered inline in the page | 115 call sites, 89 in `/mod/` |
| **D** | Legacy `core_renderer::activity_navigation()`, in-page prev / jump / next | framed layouts, non-index formats |

Key supporting facts, all verified against the source:

- `add_sticky_action_buttons()` has 6 callers, all in `/grade/import` and `/grade/export`.
  `add_action_buttons()` has 78 in `/mod/` alone. Navigation was standardised; saving was not.
- The footer is single-slot. `linearnavigationsettings::show_navigation_footer()` bails when
  `has_sticky_footer()` is already true, so a page that builds its own footer silently loses
  linear navigation as a side effect of ordering.
- `uses_linear_navigation()` returns true only for Topics and Weeks. Social and Single
  activity inherit `false` and also return `false` for `uses_course_index()`, so they fall
  through to pattern D.
- Eight activity landing pages show two bottom zones at once. `mod/book/view.php` is the
  sharpest: in-page previous/next *chapter* directly above sticky previous/next *activity*.
- Three screens split one navigation concept across both zones, using the
  `supplementary_content` slot for the "back to list" link while leaving prev/next in the page.

## Suggested next steps locally

1. Run the script against course 15. Fix whatever breaks.
2. Feed `--json` into the audit page panel, or regenerate the page's `DATA` array from it
   so the published inventory carries real IDs rather than a paste step.
3. The part a cloud session genuinely could not do: **open the screens and look at them.**
   Log in as `educator_busy` and `student_light`, walk the pattern C list, and capture what
   the divergence actually looks like. The audit so far is inferred from source, not observed.
4. Worth checking specifically, as the strongest candidates for a shared sticky footer:
   rubric and marking guide editors, `admin/roles/override.php` (save below hundreds of
   capability rows), the backup wizard's Next, and quiz attempt.

## Scope note

`footer-audit/` and `admin/cli/footer_audit_urls.php` are scratch audit tooling on this
branch, not proposed core changes. Nothing here is intended for upstream as it stands.
