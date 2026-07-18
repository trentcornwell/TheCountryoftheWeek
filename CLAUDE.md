# Claude Code Project Instructions

Read `AGENTS.md` first; it is the repository-wide authority. Then read `PROJECT.md` and the relevant specification in `docs/`. These instructions specialize the workflow for Claude Code but do not override stricter rules.

## Purpose

TheCountryOfTheWeek.com is a custom, server-rendered WordPress publication. It deterministically features one country per local New York week, beginning with Kiribati at Sunday, July 19, 2026 12:00 AM, then following the approved CIA World Factbook name order and wrapping forever.

Phase 1 establishes architecture and documentation. Do not turn the starter theme into pages or production scheduler code until explicitly asked.

## Architecture to preserve

- A versioned immutable manifest owns country identity and order.
- WordPress Country posts own editorial content and media relationships.
- A pure schedule service maps injected instants to occurrences using local calendar weeks and floor modulo.
- The theme owns semantic presentation, not imports or scheduling logic.
- A focused first-party plugin should eventually own portable domain registration and services.
- Cron may warm/purge caches but never determines correctness.
- Public requests perform no remote factual API calls or AI generation.

See `docs/DATA_MODEL.md` and `docs/SCHEDULER.md` before touching fields or time logic.

## Working style

Before editing, inspect `git status`, nearby code, applicable instructions, and existing tests. Preserve user changes and avoid broad rewrites. Explain assumptions that affect eligibility, ordering, persistence, hosting, or URLs. Prefer a small coherent patch over speculative scaffolding.

Use repository-native tools and commands when they exist. Do not install dependencies, change external services, deploy, publish, or mutate production without explicit permission. Never expose secrets in commands, logs, examples, or commits.

After editing, run the narrowest relevant checks and then broader checks in proportion to risk. State what ran, the result, and anything not verified. Do not claim a check passed if it was unavailable.

## Coding expectations

- Follow WordPress Coding Standards and project naming in `AGENTS.md`.
- Use core APIs, server rendering, contextual escaping, input validation, capabilities, and nonces.
- Isolate domain logic from templates and global WordPress state.
- Inject clocks for schedule tests and explicitly construct `DateTimeZone('America/New_York')` in future PHP code.
- Never derive order from mutable post titles and never divide epoch seconds by one week.
- Use JavaScript only as progressive enhancement and CSS as mobile-first low-specificity components.
- Write accessible semantic HTML before styling or scripting.
- Document why a non-obvious constraint exists, especially around DST, modulo, cache expiry, and manifest migrations.

## Performance requirements

Treat `docs/PERFORMANCE.md` as an acceptance contract: all Lighthouse categories above 95 on representative mobile pages, good Core Web Vitals, minimal JavaScript, responsive optimized images, no page builder, no front-end remote APIs, and boundary-aware page caching. Any new plugin or client dependency needs a measured, documented reason.

## Preferred implementation shape for later phases

Favor small namespaced PHP services with narrow interfaces, pure value objects for schedule occurrences, repository adapters for WordPress queries, and thin template/view-model functions. Favor plain CSS and tiny ES modules. Add Composer or Node tooling only when pinned, reproducible commands provide concrete value.

Do not create abstractions for hypothetical integrations. Do create explicit contracts at boundaries that are already known: manifest parsing, country lookup, clock, occurrence calculation, and rendering inputs.

## Content safety

Country facts, names, identities, sensitive demographics, prayer content, and mission information must not be guessed. Preserve provenance and flag uncertainty. AI drafts require human fact-checking, cultural/editorial review, and licensing review before publication.

