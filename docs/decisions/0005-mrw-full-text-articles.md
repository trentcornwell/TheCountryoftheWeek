# ADR 0005: Missionary Review Full Text, Split Into Articles

- Status: Accepted for implementation
- Date: 2026-08-25

## Context

ADR 0004 deliberately linked out to the original PDF scan rather than displaying any of an issue's actual text — `mrw_issue` posts carried only a short excerpt and automated country/person tags. The site owner asked for more: the full text of each issue, readable directly on the site rather than as a PDF download, and organized by article rather than as one undifferentiated wall of text, since a single monthly issue runs 30,000-50,000 words covering dozens of unrelated pieces.

## Decision

**Full text is now stored and rendered, not just linked.** The raw OCR text already sits in `SVM/text-cache/` (confirmed before starting: ~199 MB across all 668 issues, already fetched — no new downloads needed). `scripts/mrw-fetch-extract.php` now also produces a cleaned, per-article breakdown of that text; `scripts/import-missionary-review.php` writes it into `post_content` as semantic HTML (`<h2 id="article-N">` + `<p>` per paragraph) and a lightweight `article_headings` postmeta (JSON `{id, title}` pairs) for the table of contents. `single-mrw_issue.php` renders that table of contents up top and the full content below, native WordPress search now matches against real article text (not just the excerpt) since it lives in `post_content`, and `Mrw_Issue_Post_Type` now declares `editor` support so it's visible/editable in wp-admin like any other content.

**Article detection uses the magazine's own typesetting convention, not generic heading heuristics.** Inspecting real scans before building anything showed this magazine's running page header repeats the *current article's title* on every page (standard Victorian-periodical practice) — confirmed on multiple sample issues before committing to this approach. `pdftotext` (run without `-nopgbrk`) preserves page boundaries as form-feed characters, so `split_into_articles()` splits on those, reads each page's header line, strips the page-number/bracketed-date furniture around it, and groups consecutive pages whose (OCR-noise-tolerant, `similar_text()`-compared) header titles match into one article. This is a substantially more reliable signal than guessing from body-text capitalization, which was tried first and rejected as noisier.

**Sections within the issue page, not separate posts.** Explicitly decided over creating a new per-article post type/URL: article-boundary detection on 138-year-old OCR text is inherently imperfect, confirmed by hand against real output (see Consequences) — a wrong split under this design just means a messy table of contents, since every word still lands in *some* section. A separate-post design would instead risk orphaned/mis-titled pages, duplicate content across articles that got split wrong, and permalink churn every time the heuristic is retuned. Nothing new was added to the taxonomy or CPT surface; `mrw_country`/`mrw_person` tagging is unchanged.

**Still automated, still labeled as such.** The single-issue template's disclaimer now explicitly covers "the article breakdown below," not just the country/person tags — consistent with this project's standing rule (ADR 0004, `AGENTS.md`) that automated extraction from noisy historical OCR is a browsing aid, never presented as verified or editorially reviewed.

## Consequences

- Verified against a real sample (1892, April issue) before scaling: 20 articles detected with legible, plausible real titles ("A GENERATION OF CHRISTIAN PROGRESS IN INDIA", "DAVID BRAINERD"), alongside some expected over-splitting where OCR quality varied page to page within the same physical article. No content is lost in either case — over-splitting only affects how many table-of-contents entries a single article gets, never drops text.
- `post_content` is now meaningfully large per post (tens of thousands of words for some issues) — a real but ordinary size for MySQL's `longtext` column, and this is expected to grow the production database's storage footprint accordingly. This wasn't true when `mrw_issue` only stored an excerpt.
- WordPress's native search (already wired for the archive's search box via `Services\Mrw_Repository`) now matches against full article text automatically — no new search infrastructure needed, but result relevance is now subject to the same OCR noise as the displayed text.
- The original PDF is still never rehosted (ADR 0004's hosting/licensing reasoning is unchanged) — `source_pdf_url` and the "read the original scan" link remain on every page precisely so a reader can verify anything the automated cleanup or splitting got wrong.
- Re-running `mrw-fetch-extract.php` after any future tuning of the splitting heuristic is cheap (all source text is already cached locally) but re-importing to production means re-writing `post_content` for all 668 posts again — worth batching heuristic changes rather than re-deploying after every small tweak.
