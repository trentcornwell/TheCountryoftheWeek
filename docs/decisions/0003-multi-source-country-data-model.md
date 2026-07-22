# ADR 0003: Multi-Source Country Data Model

- Status: Accepted for implementation
- Date: 2026-07-22

## Context

The country manifest (`data/country-index.json`) originally sourced everything — which countries exist, their names, their `continent`/`region` classification, and by extension the ordering the manifest is walked in — from the CIA World Factbook. That left one outside organization controlling the structure of the entire site: if the Factbook's own list or regional groupings changed or were ever contested, the site's rotation, taxonomy, and browse experience would inherit that unilaterally.

## Decision

Split responsibility across four independent sources, each owning exactly one concern:

- **UN M49** (the UN Statistics Division's standard country/area and region classification) determines *which countries/areas exist* in the manifest and their `continent` (M49 Region) / `region` (M49 Sub-region, or Intermediate Region where M49 defines one) values.
- **The site's own `Rotation_Service`/`Country_Repository`** continues to determine the *weekly rotation order* — unchanged code, pure alphabetical-by-name with floor-modulo wraparound from the Kiribati anchor (ADR 0001). It has never taken its ordering from continent/region and still doesn't.
- **Joshua Project and Operation World** are this site's two named, expected sources for the Prayer & Mission content fields (`prayer_intro`, `prayer_points`, `mission_emphasis`, `prayer_source`).
- **The CIA World Factbook, World Bank, and other public sources** remain acceptable for Quick Facts and the Geography/History/Government/Economy summaries — unchanged in practice; the Factbook simply stops being the *structural* authority.

**What did not change:** country `name`s, manifest `key`s, and the manifest's alphabetical order. Only the `continent`/`region` *values* were re-sourced, matched to UN M49 by ISO alpha-2 code (reusing `data/factbook-media-codes.json`'s existing ISO mapping — the same bridge `scripts/build-country-maps.py` already uses). Reconciling all 196 entries this way changed 36 `continent` values and 118 `region` values, with zero change to which countries exist, their names, their manifest keys, or their position in the rotation. Kiribati's rotation anchor (`Country_Manifest::anchor_key` + `Country_Repository::launch_offset()`) is resolved by `manifest_key`, not derived from continent/region, so it was never at risk from this change.

**Taiwan and Kosovo** have no separate UN M49 "country or area" entry (M49 follows UN membership/recognition status; both are folded into a parent classification there). Per an explicit human decision — not a parsing fallback — both entries keep their pre-existing `continent`/`region` values unchanged (Taiwan: Asia / East Asia; Kosovo: Europe / Southern Europe). See `scripts/reconcile-m49.py`'s `MANUAL_OVERRIDES`.

`continent` adopts M49's 5-way Region model as-is (Africa, Americas, Asia, Europe, Oceania) rather than splitting the Americas back into North/South — the unmodified standard, not an added interpretation layer.

## Consequences

- No single outside organization can unilaterally change the site's country list or regional structure again — each concern has its own named, swappable source.
- `region` values are now generally more precise than the old Factbook-derived groupings (e.g. Oceania splits into Melanesia/Micronesia/Polynesia/Australia and New Zealand instead of one flat "Pacific Islands" bucket) — archive filters and "related countries" groupings changed accordingly; this was expected and reviewed, not a regression.
- `scripts/reconcile-m49.py` is the repeatable tool for this reconciliation (`--write` to apply, default is a dry-run report to `reports/m49-reconciliation.json`) should the UN M49 data or the manifest's country list ever need re-syncing. It refuses to write if any manifest entry lacks both an M49 match and an explicit `MANUAL_OVERRIDES` entry — a discrepancy is never silently guessed.
- The Quick Facts/Summaries "Source: CIA World Factbook" template label is not yet field-aware of which of the (now plural) factual sources was actually used for a given country — see `docs/CONTENT-GUIDE.md`'s "Citing sources" section for the manual workaround; a per-field source-attribution mechanism is a reasonable future improvement but out of scope here.
- `.cache/un-m49/UNSD-Methodology.csv` (git-ignored, matching `.cache/natural-earth/`'s precedent) holds the source file this reconciliation was run against, for reproducibility.
