# Weekly Scheduler Architecture

> **Implementation status (2026-07-15):** Implemented substantially as
> specified. `Services\Rotation_Service` (pure, no WordPress calls) does
> the floor-modulo week math using `DateTimeImmutable::diff()` in
> `America/New_York`, which correctly handles DST-length weeks since it
> counts actual calendar days, not `elapsed_seconds / 604800`.
> `Services\Country_Manifest` + `Services\Country_Repository` implement the
> "Decision" section below exactly: order comes from the versioned
> `data/country-index.json` manifest (each entry has a stable `key`),
> resolved to posts via `manifest_key` post meta — never by sorting live
> post titles. Verified by renaming the Kiribati post's title locally and
> confirming the rotation still resolved it correctly. One simplification:
> `tests/RotationServiceTest.php` covers the boundary/wraparound/multi-cycle
> cases but does not yet explicitly enumerate both annual DST transition
> weeks or leap-day cases — worth adding before the anchor date, not
> blocking for now since the day-counting approach isn't DST-sensitive by
> construction. No weekly-occurrence archive route or countdown script
> exists yet (the "Countdown strategy" and per-week canonical route
> sections below are not implemented); `page-schedule.php` lists the full
> rotation instead.

## Invariants

- Anchor local time: Sunday, July 19, 2026 at `00:00:00 America/New_York`.
- Anchor instant: `2026-07-19T04:00:00Z` (New York is on EDT at the anchor).
- Anchor country: Kiribati.
- Boundary: local Sunday midnight, including across daylight-saving transitions.
- Sequence: reviewed country names in deterministic alphabetical order (unchanged since launch), rotated so Kiribati has index zero. Continent/region classification comes from the UN M49 standard, not the country list's naming source — see `docs/decisions/0003-multi-source-country-data-model.md`.
- Wrap: modulo the manifest length, forever in both forward and historical calculations.

Store both the IANA time-zone identifier and anchor local date. Do not model the zone as the permanent offset `-04:00`; New York changes between EST and EDT.

## Pure calculation

Let `N` be the immutable manifest length and `w` be the signed number of local Sunday boundaries between the anchor week and the target week. The selected index is a floor modulus:

```text
index = ((w % N) + N) % N
country = rotated_manifest[index]
```

The implementation should:

1. Convert the evaluation instant to `America/New_York`.
2. Determine the start of that local schedule week (most recent Sunday at 00:00).
3. Calculate signed whole calendar weeks from the anchor local date, not elapsed seconds divided by 604800.
4. Apply floor modulus so dates before launch wrap correctly.
5. Resolve the stable manifest key to exactly one published Country post.

Calendar arithmetic is essential: a New York week containing a daylight-saving change can contain 167 or 169 elapsed hours.

## Supported queries

- **Current:** evaluate the request instant and return the matching occurrence.
- **Previous/next:** add or subtract one from the signed week index.
- **Historical:** accept a date, normalize it to the local schedule week, and calculate its occurrence.
- **Future:** same calculation with a positive signed index; no materialization required.
- **Archive:** generate a bounded range of occurrence view models (`week_index`, start, end, country key) and paginate by week.
- **Countdown:** next boundary is the next local Sunday at midnight; send its absolute timestamp to the client only if live seconds are desired.
- **First featured date:** for a country’s rotated index `i`, anchor local date plus `i` calendar weeks.
- **Next featured date:** calculate the smallest occurrence for that country whose start is strictly after the evaluation instant.

The canonical page for an occurrence should have a stable date-based route if weekly archives require separate indexable pages. Country pages and weekly occurrence pages solve different URL semantics; do not create duplicate content without canonicals.

## Countdown strategy

Render the next change time and human-readable duration on the server. A tiny progressive-enhancement script may update the visible countdown once per second or minute, using an ISO instant supplied by the server. The schedule remains authoritative on the server. On reaching zero, refresh or refetch a small endpoint; do not let client clock arithmetic select the featured country.

## Caching and boundary behavior

- Include the occurrence/week index in cache keys or purge relevant public caches at the boundary.
- Set an HTTP/cache TTL that cannot serve the old country beyond Sunday midnight, with a safety margin for clock skew.
- Schedule a WordPress cron purge as an optimization only. WP-Cron is traffic-driven and is not the source of truth.
- On DreamHost, a real system cron may warm/purge caches just after the boundary if available.
- Keep server, PHP, WordPress, and monitoring clocks synchronized; WordPress timezone must be `America/New_York`.

## Dynamic calculation vs permanent JSON schedule

### A. Calculate on every request

Advantages: constant-size data, infinite past/future support, no regeneration, and no scheduled write. The arithmetic is negligible and can be memoized within a request or cached with rendered pages.

Risks: date math must be correct; changing the manifest changes schedule meanings unless the manifest is versioned and frozen.

### B. Store a permanent JSON schedule

Advantages: each generated occurrence is inspectable and can preserve decisions after input changes. Static consumers can read it without implementing date math.

Risks: no finite file is permanent for an infinite schedule; it needs a horizon, regeneration, collision rules, large diffs, deployment coordination, and still needs correct date generation. A stale file creates avoidable failure modes.

## Decision

Choose **A: deterministic dynamic calculation**, backed by a committed, versioned, immutable country manifest and anchor configuration. Do not calculate by repeatedly sorting WordPress post titles: that allows editorial changes to rewrite history.

Optionally generate bounded JSON snapshots (for example, five years) as build artifacts for auditing, feeds, or external consumers. Those snapshots are derived views, never the runtime source of truth. This hybrid preserves inspectability without pretending an infinite schedule can be materialized.

See `docs/decisions/0001-deterministic-weekly-schedule.md`.

## Manifest change policy

A manifest version becomes immutable once used in production. If CIA naming or eligibility changes, create a new version and an explicit effective boundary. The schedule resolver must retain old versions for historical URLs and apply the new version only from its approved effective week. A migration ADR must state whether sequence continuity or a deliberate reset is intended.

## Failure modes

- Missing or duplicate Country post: log, alert, and show a controlled unavailable state; never substitute.
- Invalid timezone or anchor: fail validation during bootstrap/deployment.
- Cache not purged: TTL limits damage; synthetic monitoring checks the expected stable key after the boundary.
- Manifest mutation: CI compares checksum/version and requires an approved migration decision.

## Required tests

- Exact instants immediately before, at, and after launch.
- Saturday-to-Sunday boundaries in EST and EDT.
- Both US daylight-saving transition weeks.
- Negative indices and dates before the anchor.
- Last country to Kiribati wraparound and multiple full cycles.
- Leap days and distant future dates.
- First/next featured-date semantics at exact boundaries.
- Archive pagination without gaps or duplicates.
- PHP/WordPress process default timezone differing from the site timezone.

