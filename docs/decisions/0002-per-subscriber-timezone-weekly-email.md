# ADR 0002: Per-Subscriber Time Zone for the Weekly Upcoming-Country Email

- Status: Accepted for implementation
- Date: 2026-07-21

## Context

Registered subscribers should receive an email every Saturday at 12:00 PM in their own local time zone, previewing the country that becomes active the following Sunday. Every existing clock in this theme (`Rotation_Service`, `Utilities\Date_Utility`) reasons about a single fixed `America/New_York` instant; this feature introduces the first per-user time zone, and the first cron job, in the codebase. `PROJECT.md` explicitly forbids "a cron job that changes a global 'current country' option each Sunday," so the new cron must only *read* the derived upcoming country, never cache or decide one.

## Decision

**Which country to feature** is never re-derived: `Services\Subscriber_Notifier` composes `Country_Repository::get_by_offset(Country_Repository::get_active(), 1)`, the existing pure list-position lookup. This composition is timezone-safe by construction — evaluating it at "Saturday local noon" in any IANA time zone, even the UTC+14/UTC-12 extremes, always converts to an instant within Friday-evening-through-Saturday-evening `America/New_York` time, always strictly before the Sunday 00:00 NY boundary that flips `get_active()`. No per-time-zone special-casing is needed for *which* country to feature.

**When to send** is the only new timing logic, isolated in `Services\Subscriber_Notification_Schedule` — a pure, clock-injectable predicate (no WP calls), styled after `Rotation_Service` for the same testability reasons. It takes the exact boundary instant (`Country_Repository::next_scheduled_date($upcoming)`, passed in rather than recomputed) and answers "should this subscriber, in their own time zone, be notified right now."

The notification window is `[most recent local Saturday 12:00, $boundary)` — capped at the boundary, not open-ended. The "Saturday-local-noon is safe" property above only holds up to the moment the NY clock itself crosses the boundary; past that, `get_active()` flips and what was "upcoming" is now current. Capping the window there avoids ever needing to handle that case.

This wide window (not a strict single-hour check) is also what makes the cron job self-healing: WP-Cron's `hourly` schedule is traffic-driven and can drift or miss a tick entirely (already an accepted limitation — see `docs/SCHEDULER.md`). If a run is missed, the next run that does fire still finds the predicate true and sends, a bit late but correct. A per-subscriber `country_week_last_notified_week` meta value (the boundary's date) then prevents any repeat.

**This idempotency meta is deliberately not the same kind of thing PROJECT.md forbids.** It never determines *which* country is active or upcoming — it only remembers "have I already sent this specific subscriber this specific week's email." If it were ever lost or wrong, the worst outcome is one subscriber getting a duplicate or missed email, never an incorrect rotation answer for anyone.

## Consequences

- Two new pieces of WordPress user meta (`country_week_timezone`, `country_week_email_optin`) — the theme's first use of user meta; both `show_in_rest => false` since they're private preferences, not editorial content.
- The theme's first cron job (`Hooks\Weekly_Email_Hooks`, hourly WP-Cron). Precision is intentionally loose; correctness does not depend on the cron firing at any particular minute.
- Timezone math needs strong tests at the extremes (UTC+14, UTC-12), at the boundary edge, and across DST transitions — see `tests/SubscriberNotificationScheduleTest.php`.
- Existing subscribers (registered before this feature existed) default to `America/New_York` until they visit `/email-preferences/` — an accepted imprecision, not a correctness bug, since the window is wide enough that a wrong-but-fixed-zone send still lands within a reasonable margin of Saturday.
- Existing subscribers are opted in by default, matching new signups — a deliberate product decision, not an engineering default.
