# How Kiribati's Launch Content Was Authored

## Process

Kiribati's content (`data/kiribati.json`) was drafted by Claude directly
from general knowledge during this build session — **not** by fetching or
scraping the live CIA World Factbook page. No web request was made to
cia.gov or any other source as part of generating this content.

This matters because `AGENTS.md`'s content-safety rules are explicit:
factual claims must not be guessed, and AI drafts require human
fact-checking before publication. **`data/kiribati.json` is a draft that
has not yet had that review.** Treat every number (population, life
expectancy, area) and every claim (history, government structure) as
needing verification against the actual current CIA World Factbook entry
for Kiribati (https://www.cia.gov/the-world-factbook/countries/kiribati/)
and at least one secondary source before this goes live to real visitors.

## What still needs human review before publishing

- [ ] Verify every Quick Facts value against the current Factbook entry
      (population and life expectancy figures drift year to year)
- [ ] Verify the history/government/economy summaries for factual accuracy
- [ ] Cultural sensitivity review of the "People" and "Culture" framing
- [ ] Theological/pastoral review of `prayer_intro`, `prayer_points`, and
      `mission_emphasis` — these are original text, not copied from any
      copyrighted source (per the project's explicit requirement not to
      auto-import Operation World or similar content), but original text
      about a specific culture's faith life still warrants review by
      someone with pastoral/missiological background before publication
- [ ] Source and license flag/map/hero images before uploading them (none
      are included yet — `flag_image_id`/`map_image_id`/gallery are empty)
- [ ] Fact-check `suggested_reading` links resolve to what their titles claim

## Reusable prompt shape for the remaining 195 countries

To draft another country's `data/<key>.json` file with an AI assistant,
provide:

1. The exact Factbook country name and manifest `key` from
   `data/country-index.json`.
2. `data/countries.schema.json` (the field-by-field shape to fill in).
3. An explicit instruction that population/area/currency/etc. must be
   flagged as "needs verification against a live source" rather than
   stated as fact, since the assistant has no live data access unless a
   web-fetch tool is explicitly used and cited.
4. An explicit instruction that prayer/mission content must be original
   framing, never copied from Operation World or other copyrighted
   sources — see `AGENTS.md`.
5. This document's review checklist, so the draft is clearly marked
   unpublished-pending-review, not treated as ready-to-ship.

The resulting file goes through the same import path documented in
`docs/CONTENT-GUIDE.md`: save as `data/<key>.json`, run
`wp eval-file scripts/import-countries.php`, then complete the review
checklist above before the post (if drafted) is published.
