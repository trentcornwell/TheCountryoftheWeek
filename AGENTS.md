# Instructions for AI and Human Contributors

## Authority and scope

These instructions apply to the entire repository. More specific `AGENTS.md` files may add constraints within a subdirectory but may not weaken security, accessibility, attribution, or schedule invariants. Claude Code, Codex, ChatGPT, and future agents must read this file, `PROJECT.md`, and the relevant specification before editing.

**Status update (2026-07-15): the project has moved past Phase 1.** The user
provided the full product spec and explicitly directed implementation to
proceed through pages, the scheduler, the country manifest, and content
import. See `PROJECT.md`'s "Status: what's built vs. what remains" section
for the current state. The phase-gate language below is preserved as
historical planning context; treat "Phase 1 only" statements throughout
this file as superseded by that status update, not as current instruction.

## Required workflow

1. Inspect repository status and existing work before editing. Never overwrite unrelated user changes.
2. State assumptions when requirements are ambiguous; ask before decisions that change product policy, data eligibility, hosting topology, or external systems.
3. Make the smallest coherent change and keep domain, presentation, and operations boundaries intact.
4. Update documentation, tests, schemas, and decision records with behavior changes.
5. Run proportionate validation and report exactly what ran and what remains unverified.
6. Never publish, deploy, send messages, mutate production data, or incur service costs without explicit authorization.

## Architecture rules

- WordPress is the content-management and server-rendering platform; do not introduce a SPA or headless layer without an accepted ADR.
- The featured country is derived from the anchor, local calendar week, and versioned immutable manifest. Never store a manually editable “current country” as the authority.
- Use `America/New_York`, never a fixed UTC offset. Use calendar-week arithmetic, never elapsed seconds divided by 604800.
- Templates render view models and do not contain schedule, import, or data-normalization logic.
- Public requests must not scrape, call remote factual APIs, generate AI content, or perform schedule writes.
- Prefer a small first-party domain plugin for portable content types and schedule services; the theme owns presentation.
- Use stable internal identifiers. Slugs and display names are not durable foreign keys.
- Treat manifest ordering as historical data. Any production reorder requires a versioned migration ADR.
- Prefer WordPress core APIs and no dependency over a plugin or library. Document every dependency’s purpose, license, maintenance status, and removal path.
- All time, network, filesystem, and database dependencies must be injectable or isolated enough to test.

## Naming conventions

- Theme text domain and slug: `country-week`.
- PHP namespace for future object-oriented domain code: `CountryWeek\` with PSR-4-style class names when Composer is adopted.
- WordPress global symbols, hooks, options, and handles use the `country_week_` prefix (see `theme/country-week/functions.php`, `includes/class-theme.php`).
  **Known deviation:** editorial Country meta keys (`capital`, `population`,
  `prayer_points`, etc. — the full list is `CPT\Country_Meta_Fields::all_fields()`)
  were implemented unprefixed for readability in `wp_query`/template code.
  The internal `manifest_key` meta (see `Services\Country_Manifest`) is also
  unprefixed. This is a real deviation from this rule, accepted rather than
  reworked across ~15 files; if a future plugin ever collides with one of
  these keys, prefix at that point rather than preemptively.
- Custom post type: `country`; taxonomy slugs use singular `snake_case` identifiers and must remain within WordPress length limits.
- PHP classes: `PascalCase`; methods/functions/variables: `snake_case` when integrating with WordPress conventions; constants: `UPPER_SNAKE_CASE`.
- JavaScript modules/functions/variables: `camelCase`; classes: `PascalCase`; constants: `UPPER_SNAKE_CASE`.
- CSS classes: project-prefixed BEM-like names such as `cw-card`, `cw-card__title`, and `cw-card--featured`. Avoid styling by generated WordPress IDs.
- Files and directories: lowercase kebab-case unless an ecosystem convention requires otherwise.

## PHP standards

- Follow the WordPress PHP Coding Standards. Add strict typing only in files where compatibility and WordPress integration are understood; never add it mechanically to template files.
- Target the documented minimum PHP version and do not use newer syntax until CI and DreamHost support it.
- Use strict comparisons, early returns, narrow functions, explicit return types where compatible, and dependency injection for clocks/services.
- Sanitize and validate at input, authorize mutations with capabilities and nonces, use `$wpdb->prepare()` for dynamic queries, and escape at the final output context.
- Use `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`, and internationalization functions correctly. Never escape a value once and reuse it in a different context.
- Do not suppress errors, use PHP sessions, rely on global timezone mutation, or query the database directly when a suitable WordPress API exists.
- Register post meta with type, single/multiple behavior, defaults, sanitization, authorization callback, and REST schema where applicable.
- Cache only derived/read data with explicit invalidation and schedule-boundary behavior.

## WordPress standards

- Support maintained WordPress versions agreed in the compatibility matrix; test upgrades before changing the minimum.
- Use hooks, template hierarchy, enqueue APIs, Settings API, Metadata API, media APIs, REST schemas, and roles/capabilities rather than replacing core behavior.
- Never edit WordPress core or bundled third-party code.
- Prefix all global identifiers and ensure activation/deactivation/uninstall behavior is documented for any plugin.
- Avoid `query_posts`; reset custom query state; prevent N+1 metadata/media queries; paginate archives.
- Use WP-Cron only for best-effort maintenance. Correct featured-country selection must not depend on cron firing.
- Translation-ready user-facing strings belong in the `country-week` text domain.
- Keep admin/editor code out of front-end requests where practical.

## JavaScript standards

- Use JavaScript only for progressive enhancement; core content and navigation must work without it.
- Prefer browser APIs and small ES modules. Do not add jQuery, a framework, transpilation, or a package solely for trivial behavior.
- Preserve keyboard access, focus, reduced-motion preferences, and semantic control behavior.
- Do not use inline executable scripts, `eval`, unsafe HTML insertion, or client-side factual/schedule authority.
- Keep modules side-effect-light and test date/countdown behavior with an injected clock.
- Lint with the repository’s future pinned ESLint configuration; do not introduce tooling without documenting exact commands.

## CSS standards

- Mobile first, progressively enhanced, and organized around design tokens and components.
- Prefer logical properties, modern layout, relative units, and low-specificity selectors. Avoid `!important`, deep nesting, and utility proliferation.
- Meet WCAG 2.2 AA contrast, visible focus, target size, zoom/reflow, and reduced-motion requirements.
- Reserve image dimensions to prevent layout shift. Prefer system fonts; do not import remote fonts from CSS.
- Remove unused CSS and load template-specific styles conditionally where it materially improves the budget.

## Data and content standards

- Facts require source URL/identifier, retrieval date, relevant as-of date, and review status.
- Never fabricate CIA identifiers, country membership, citations, population values, leaders, or religious/mission claims.
- AI-generated text is a draft. Human review is required for factual accuracy, tone, cultural sensitivity, theology/prayer content, rights, and publication.
- Do not commit copyrighted media without documented license and attribution.
- Imports are idempotent, dry-runnable, diffable, and must not overwrite reviewed prose silently.

## Testing and performance

- Add unit tests for pure logic and regression tests for each fixed bug.
- Inject time; do not let tests depend on the current clock or a live external service.
- Cover anchor, exact boundary, DST, pre-anchor negative weeks, wraparound, and manifest versioning.
- Maintain the budgets in `docs/PERFORMANCE.md`. New JavaScript, plugins, remote requests, or render-blocking assets require measured justification.
- Accessibility requires automated checks plus manual keyboard and screen-reader verification before release.

## Documentation

- Document public services, hooks, schemas, commands, environment assumptions, migrations, failure behavior, and rollback.
- Comments explain why, invariants, or non-obvious constraints—not syntax.
- Keep `README.md` focused on onboarding; `PROJECT.md` on system direction; `docs/` on detailed contracts; ADRs on consequential decisions.
- Use ISO 8601 dates and always include an IANA timezone for local schedule times.
- Links and examples must be safe placeholders when they are not verified sources.

## Git workflow

- `main` is protected and deployable. Work in short-lived branches named `feature/...`, `fix/...`, `docs/...`, or `chore/...`.
- Rebase/update before review according to repository policy; do not rewrite shared history.
- Keep commits focused. Do not commit generated exports, dependencies, secrets, uploads, database dumps, or unrelated formatting.
- Pull requests explain outcome, validation, architectural impact, risk, migration, and rollback.
- Never force-push, deploy, merge, tag, or open an external pull request unless explicitly authorized.

## Commit messages

Use Conventional Commits:

```text
type(optional-scope): imperative summary
```

Allowed common types: `feat`, `fix`, `docs`, `test`, `refactor`, `perf`, `build`, `ci`, and `chore`. Examples: `docs(schedule): define DST boundary behavior` and `test(schedule): cover negative week modulo`. Add a body for rationale and a `BREAKING CHANGE:` footer for contract changes.

## Definition of done

A change is complete only when it follows the documented architecture, handles failure and security contexts, includes appropriate tests, passes applicable checks, preserves performance/accessibility budgets, updates documentation/ADRs, and reports any unverified assumptions.

