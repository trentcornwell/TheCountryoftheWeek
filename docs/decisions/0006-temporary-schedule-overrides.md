# ADR 0006: Temporary Schedule Overrides

- Status: Accepted for implementation
- Date: 2026-08-28

## Context

The editorial calendar occasionally needs specific countries on specific weeks that don't match the perpetual alphabetical order — for example, featuring Lebanon through Mayotte in a particular sequence between August and December 2026, including two French overseas territories (Martinique, Mayotte) that aren't sovereign countries and were never part of `data/country-index.json`'s 196-country manifest.

ADR 0001 deliberately makes the manifest append-only/frozen once live: reordering it requires a new `manifest_version` and a migration record, because the schedule is derived, not stored, and a silent reorder would rewrite history for every week after the change. That policy is right for *permanent* reordering, but it's the wrong tool for a bounded, near-term editorial exception — bumping the manifest version to swap eighteen weeks of order would permanently change the position of every country that follows them, forever, for what is fundamentally a temporary request.

## Decision

Add a small, explicit, git-tracked exception list — `data/schedule-overrides.json` (bundled into the theme as `includes/data/schedule-overrides.json`, same pattern as the manifest) — mapping specific local schedule weeks (Sunday, America/New_York) to a manifest key. `Services\Country_Repository` consults it before falling back to the normal alphabetical computation; `Services\Rotation_Service` stays exactly as pure and untouched as ADR 0001 left it — it still knows nothing about overrides, WordPress, or posts.

The country that would have naturally aired an overridden week is never lost or reshuffled: it's simply skipped for that one cycle and airs on its own next natural occurrence, one full rotation length later (`Country_Repository::next_scheduled_date()`/`most_recent_date()` compute this directly — see `schedule_date_for()`). Nothing about any other country's position changes, and the manifest itself is never touched.

Two of the eighteen entries (Martinique, Mayotte) reference posts that are outside `data/country-index.json` entirely — created instead from `data/one-off-features.json`, imported by `scripts/import-countries.php` with the same idempotent logic as manifest countries, but never added to the manifest, so they never participate in rotation order, count, or position math for anyone else. They only ever appear on the site because a `schedule-overrides.json` entry points at them directly.

An override whose target post isn't published yet is never silently substituted with the wrong country — `Country_Repository::get_active()` logs the gap and falls back to the natural schedule instead.

## Consequences

- `data/schedule-overrides.json` is for bounded, near-term editorial exceptions only. It must not be used to permanently reorder the rotation — that's still ADR 0001's manifest-version-and-migration path.
- Every place that used to compute "is this country active" from raw list position (`page-schedule.php`, `single-country.php`, `templates/parts/country-card.php`, `includes/admin/class-admin-columns.php`) now compares against `Country_Repository::get_active()`'s resolved post identity instead, since an override breaks the assumption that a country's alphabetical position always matches its calendar week.
- `Country_Repository::get_active()` is now cached per request (it previously computed a value cheap enough not to need it); the country archive page calls it once per card in a loop of up to 196.
- A country whose slot is overridden away, or whose own override lands earlier than its natural slot, has schedule-affecting date methods (`next_scheduled_date()`, `most_recent_date()`) that diverge from pure position math for exactly the weeks `schedule-overrides.json` touches, and settle back to ordinary behavior once those dates pass out of the file.
