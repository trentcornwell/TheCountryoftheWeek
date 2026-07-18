# Project Architecture

## Product boundary

The site is an editorial WordPress publication, not a real-time country-data
service. WordPress owns authored content, media relationships, revisions,
preview, permissions, and presentation. A versioned country manifest owns
stable identity and sort order. The featured country is derived from an
anchor instant and week index; it is not manually selected and does not
require a successful cron event.

## Architecture

The system has five layers, all implemented:

1. **Canonical data** — `data/country-index.json` is the reviewed, versioned
   manifest of eligible CIA World Factbook country names, each with a stable
   `key` (independent of the editable post title) and deterministic order.
   The theme ships its own frozen copy at
   `theme/country-week/includes/data/country-manifest.json` so it can
   resolve rotation order without depending on anything outside the
   deployable theme directory. `scripts/import-countries.php` keeps the two
   in sync.
2. **Content domain** — `theme/country-week/includes/cpt/` registers the
   `country` post type, the `continent`/`region` taxonomies, and all
   metadata. This currently lives in the theme rather than a separate
   first-party plugin (see "Deviations from the original plan" below).
3. **Schedule domain** — `Services\Rotation_Service` is a pure service
   (no WordPress calls) that maps a timestamp to a week index and that
   index to a rotation position using floor modulo.
   `Services\Country_Repository` resolves rotation positions to actual
   `WP_Post` objects via `Services\Country_Manifest`, and exposes previous,
   next, archive, and schedule-date queries.
4. **Presentation** — `theme/country-week/` templates render semantic,
   server-side HTML and structured data. Templates consume the domain
   services above and contain no scheduling arithmetic themselves.
5. **Operations** — `scripts/import-countries.php` is the deterministic,
   idempotent importer; `tests/RotationServiceTest.php` covers the
   scheduler; `.wp-local/` (git-ignored) is a local verification
   environment. CI and a production deployment pipeline are not yet built
   — see `docs/DEPLOYMENT.md`.

### Request flow

For a public request, WordPress obtains the current site timestamp,
`Rotation_Service` calculates the current rotation position,
`Country_Repository` resolves that position to one published Country post
(via the manifest, not the post title), and the theme renders the page. If
no country is published yet (e.g. mid-import) or the rotation hasn't
started, the front page renders an explicit "featuring begins" state — it
never silently substitutes a different country.

### Source-of-truth rules

- `data/country-index.json` is authoritative for membership, stable
  identity (`key`), and order. Its bundled copy inside the theme
  (`includes/data/country-manifest.json`) is what the live site actually
  reads from.
- The anchor is `2026-07-19T00:00:00` local time, `America/New_York`
  (`2026-07-19T04:00:00Z`), country key `kiribati`.
- WordPress Country posts are authoritative for publishable content and
  media. A post's `manifest_key` meta (set once at import) ties it to its
  manifest entry; renaming the post's title never changes its rotation
  position — see `docs/decisions/0001-deterministic-weekly-schedule.md`.
- Derived dates (week number, next/previous scheduled date) are calculated
  by `Rotation_Service`/`Country_Repository`, never editorially entered.
- Secrets and environment-specific values live in hosting configuration,
  never Git.

## Folder responsibilities

| Path | Responsibility |
| --- | --- |
| `theme/country-week/` | The deployable WordPress theme: templates, `includes/` (CPTs, services, forms, SEO, admin, hooks), assets, and the bundled manifest copy |
| `docs/` | Architecture, contracts, decisions, and setup/deployment guides |
| `prompts/` | How AI-assisted content (e.g. the Kiribati launch content) was sourced; generated prose is a draft requiring human review |
| `scripts/` | The country importer (idempotent, dry-run-safe by re-running against unchanged data) |
| `data/` | The canonical manifest, its JSON shape documentation, and per-country content files |
| `exports/` | Disposable generated output; ignored by Git except its README |
| `tests/` | PHPUnit coverage for the rotation engine |
| `.github/` | Pull request template; CI workflows not yet implemented |
| `.vscode/` | Shared, non-personal editor recommendations |
| `.wp-local/` | Git-ignored local WordPress install used only to verify the theme (see `docs/SETUP.md`) |

## Design philosophy

- Determinism over scheduled mutation: the correct country is always
  recoverable from time and immutable inputs, never a stored "current
  country" option.
- Boring technology over novelty: WordPress and browser primitives before
  dependencies. The only vendored third-party code is a small MIT-licensed
  QR encoder (`includes/vendor/qr-code-generator.php`) used for the
  printable country sheet.
- Content portability: domain content lives in registered post meta, not
  template code.
- Explicit boundaries: identifiers, time zones, schemas, and failure
  behavior are documented.
- Accessibility and performance are architectural constraints — semantic
  HTML, minimal JS, no page builder, responsive images.
- Human editorial accountability: AI may assist research and drafting
  (see `prompts/kiribati-content-prompt.md`), but claims require source
  review before publication.

## Status: what's built vs. what remains

This project has moved past the original "Phase 1 — Foundation" planning
stage into a working implementation. As of this document:

**Built and verified locally:**
- Country custom post type, `continent`/`region` taxonomies, full metadata
  model (`includes/cpt/`)
- The manifest-driven, rename-safe rotation engine (`Rotation_Service`,
  `Country_Manifest`, `Country_Repository`) with unit tests
- All 196 CIA World Factbook countries imported as published posts, with
  Kiribati fully content-authored as the launch country — **Kiribati's
  content is an unreviewed AI draft** (written from general knowledge, not
  fetched from a live source) and needs the fact-check/sourcing/cultural
  review documented in `prompts/kiribati-content-prompt.md` before it's
  truly launch-ready
- Homepage, single country, archive (search/filter), schedule, and
  suggest-an-edit templates
- Printable country sheet (browser print-to-PDF, no PDF library) with a
  QR code back to the canonical URL
- Suggest-an-Edit form with honeypot/timing/nonce spam prevention, stored
  as a moderatable `edit_suggestion` post type, emailed to the admin
- Hand-rolled SEO (JSON-LD, Open Graph/Twitter tags, meta description) and
  performance hygiene (lazy loading, head cruft removal)
- Admin UX: custom list columns, grouped meta boxes, a read-only schedule
  dashboard widget

**Not yet built** (see the docs referenced for what's specified):
- CI checks, WordPress Coding Standards enforcement, and the DreamHost
  deployment pipeline described in `docs/DEPLOYMENT.md`
- The richer structured fact model (`{value, source, as_of, review_status}`
  per fact) described in `docs/DATA_MODEL.md` — the current implementation
  stores Quick Facts as plain sourced text rather than that full envelope;
  revisit if per-fact provenance tracking becomes a real editorial need
- A first-party domain plugin separating content registration from the
  theme (currently both live in `theme/country-week/`, which `PROJECT.md`
  always allowed as a starting point)
- Full content for the 195 non-launch countries (published as stubs;
  see `docs/CONTENT-GUIDE.md`)
- Page-level HTTP caching and boundary-aware cache purging described in
  `docs/PERFORMANCE.md`

## Future roadmap (not started)

- **Missionary names per country.** The ministry wants to eventually associate
  the names of Baptist missionaries with the countries they serve in. Not
  built yet — when this is picked up, it likely wants its own field group
  (or a lightweight relationship to a future `missionary` post type) rather
  than overloading the Prayer & Mission fields, since missionary identity/
  safety considerations may require different visibility rules than public
  prayer content (e.g. a missionary serving in a closed country may need
  their name and location handled with more care than a public byline).
  Flag this explicitly before building it.

## Non-goals

- A page builder, SPA, headless WordPress architecture, or JavaScript-first
  rendering
- Live CIA API calls on public requests
- A cron job that changes a global "current country" option each Sunday
- An infinite materialized schedule (the manifest is bounded and finite;
  the schedule itself is computed, not stored, per country)
- Unsourced AI-generated factual content published without human review
