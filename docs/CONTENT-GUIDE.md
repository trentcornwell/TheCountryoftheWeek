# Content Management Guide

## The two ways to edit country content

### 1. Directly in wp-admin (for one-off edits)

Countries → find the country → each field group (Quick Facts, Summaries,
Facts & Lists, Prayer & Mission, Media, Photo Gallery) is its own meta box.
List-type fields (Interesting Facts, Did You Know, Suggested Reading,
Prayer Points) take one item per line — see the description text under each
field. Suggested Reading lines use the format `Title | https://example.com`;
a line with no `|` displays as plain text with no link.

Renaming a country's title in wp-admin is always safe — it only changes the
display name. Rotation order is tracked by an internal, hidden
`manifest_key` field that never changes (see "How rotation order works"
below), so a title correction can never accidentally reorder the schedule.

### 2. Through `data/*.json` + the importer (for bulk/repeatable authoring)

This is how Kiribati's launch content was authored (see
`prompts/kiribati-content-prompt.md`) and is the recommended path for
filling in the remaining 195 countries over time.

1. Copy `data/kiribati.json` as a template.
2. Fill in `name` (must exactly match an entry in `data/country-index.json`),
   `excerpt`, and the four content groups (`quick_facts`, `summaries`,
   `facts_and_lists`, `prayer_and_mission`). See `data/countries.schema.json`
   for the full field-by-field shape.
3. Save it as `data/<country-key>.json` (e.g. `data/japan.json`).
4. Run the importer:
   ```bash
   wp eval-file scripts/import-countries.php
   ```
   This applies the file's content to the matching post (matched by name,
   pinned to that post's `manifest_key` from then on) and reports success/
   failure for each file. It's idempotent — re-running after editing a
   content file just updates the same fields again.

**Never paste copyrighted content** (Operation World, published books,
etc.) into `prayer_intro`, `prayer_points`, or `mission_emphasis`. Write
original summaries or use explicitly licensed material only — see
`AGENTS.md`'s "Content safety" section.

## How rotation order works

- `data/country-index.json` is the canonical, versioned manifest: every
  country's official Factbook name, a stable `key` (e.g. `kiribati`,
  `korea-north`), continent, and region, listed in alphabetical order.
- Running the importer copies this file into
  `theme/country-week/includes/data/country-manifest.json` (the theme's own
  bundled copy — this is what the live site actually reads) and stamps each
  matching post's hidden `manifest_key` meta field.
- `Services\Country_Repository::get_all_ordered()` builds the rotation list
  by walking the manifest in order and looking up each post by its
  `manifest_key` — never by sorting live post titles. See
  `docs/decisions/0001-deterministic-weekly-schedule.md` for why.

**Adding a new country after launch:** append it to the end of
`data/country-index.json` with a new unique `key`, bump `manifest_version`,
document why in a new ADR under `docs/decisions/`, and re-run the importer.
Appending to the end (rather than inserting alphabetically) preserves every
existing country's rotation position — inserting in the middle would shift
everyone after it. This is a deliberate trade-off: strict alphabetical
purity vs. schedule stability. Prefer stability once the site is live.

**Never** reorder or remove entries from an already-launched manifest
in place — that changes historical and future results for every country
after the change point. If a name genuinely needs correcting, edit the
`name` field only; the `key` (and therefore the rotation position) must not
change.

## Moderating Suggest an Edit submissions

Submissions appear under **Edit Suggestions** in the admin menu (not
publicly visible). Each one records the submitter's name/email, which
country, the suggested correction, and an optional source/URL. The site
admin email also receives a notification for each submission. There's no
built-in "resolved" workflow beyond WordPress's own post status — move
handled suggestions to Trash once addressed, or extend
`CPT\Edit_Suggestion_Post_Type::STATUS_META_KEY` with an admin UI if a
formal review pipeline becomes worthwhile.

## Citing sources on country pages

Quick Facts and the Geography/History/Government/Economy summaries are
labeled "Source: CIA World Factbook (public domain)" automatically whenever
those fields have content — see `templates/parts/quick-facts.php` and
`templates/parts/summaries.php`. Nothing to do there.

The Prayer & Mission section only shows a source credit if you fill in the
**Prayer Content Source** field (e.g. `Operation World`) in that country's
meta boxes. Only set this when the prayer content is actually adapted or
quoted from that specific source, kept brief, and properly attributed —
never for original team-written content, and never as a way to bulk-copy a
licensed source's full text. Leave it blank for original writing.

## Moderating "Join Us in Prayer" signups

Submissions from `/join-us-in-prayer/` appear under **Prayer Partners** in
the admin menu (not publicly visible), with name, church, email, and when
they said they started praying with us. The admin email also receives a
notification for each signup — the form promises to send "additional
helpful resources," so follow up manually; there's no automated resource
delivery. A `resources_sent` meta field (defaults to `no`) exists on each
entry if you want to track who's been followed up with.

## Media

- **Flag / Location Map**: set per-country via the Media meta box (native
  WordPress media picker, no plugin).
- **Hero image**: the post's normal Featured Image.
- **Photo Gallery**: the Photo Gallery meta box's "Add Images" button
  (multi-select, native media library).

Always fill in alt text via the media library's own attachment fields —
templates rely on it for accessibility (see `Templates\parts\gallery.php`,
`hero.php`).
