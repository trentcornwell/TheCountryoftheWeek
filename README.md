# The Country of the Week

TheCountryOfTheWeek.com is a custom WordPress publication that introduces one country each week. The sequence begins with Kiribati at `2026-07-19 00:00:00 America/New_York`, proceeds alphabetically by CIA World Factbook country name, and wraps forever without editorial intervention.

**Status (2026-07-15):** past the original Phase 1 planning stage — the theme, the rotation scheduler, the country manifest/importer, and all 196 country posts (Kiribati fully content-authored, the rest as published stubs) are built and verified against a local WordPress install. See [PROJECT.md](PROJECT.md)'s "Status: what's built vs. what remains" section for the precise current state, and [docs/SETUP.md](docs/SETUP.md) for how to run it locally. CI and the production deployment pipeline described in `docs/DEPLOYMENT.md` are not yet built.

## Goals

- Make the featured country deterministic for past, present, and future weeks.
- Deliver a fast, accessible, mobile-first, server-rendered WordPress experience.
- Keep structured country facts separate from editorial and devotional content.
- Minimize plugins, JavaScript, operational work, and deployment risk.
- Make decisions and constraints discoverable to human and AI contributors.
- Preserve source attribution, update dates, and editorial provenance.

## Requirements

- PHP and WordPress versions supported by the production DreamHost environment. Pin exact versions before implementation begins.
- MySQL or MariaDB supported by that WordPress version.
- Git, a local WordPress environment, and VS Code are recommended.
- Node.js and Composer should be introduced only when the implementation needs reproducible linting or build tooling.

## Installation

See [docs/SETUP.md](docs/SETUP.md) for the full, tested local setup (including a
zero-Docker/zero-MySQL path used to develop and verify this theme). Summary:

1. Clone this repository into a local workspace.
2. Stand up a local WordPress installation — see `docs/SETUP.md` for a documented
   Docker-free option, or use Local/DDEV/another environment of your choice.
3. Link or copy `theme/country-week` to `wp-content/themes/country-week` (or register
   it as an external theme directory — see `docs/SETUP.md`).
4. Activate **The Country of the Week** in WordPress.
5. Set **Settings > General > Timezone** to `America/New_York` (not a fixed UTC offset).
6. Run `wp eval-file scripts/import-countries.php` to create all 196 country posts.
7. Keep WordPress core, uploads, secrets, and environment configuration outside this repository.

## Local development

- Branch from `main`; use short-lived branches and focused commits.
- Read `AGENTS.md` before changing code and the relevant document under `docs/` before changing an architectural contract.
- Do not edit WordPress core or commit `wp-config.php`, database dumps, uploads, credentials, generated exports, or dependencies.
- Run the future lint, unit, integration, accessibility, and performance checks before opening a pull request.
- Use representative fixture data in tests; never depend on live CIA pages during a request or test.

The `.vscode` directory contains conservative workspace recommendations and intentionally does not force a specific local WordPress stack.

## Deployment

The intended workflow is GitHub-driven and artifact-based:

1. A pull request runs automated quality checks.
2. An approved change is merged to `main` and tagged.
3. CI builds a clean theme ZIP from `theme/country-week`.
4. The artifact is deployed to DreamHost staging over SSH/SFTP.
5. Smoke tests and cache checks run against staging.
6. The same immutable artifact is promoted to production, followed by a rollback-ready verification.

Do not deploy a developer working tree or synchronize the whole WordPress installation. See `docs/DEPLOYMENT.md`.

## Folder organization

| Path | Responsibility |
| --- | --- |
| `theme/country-week/` | The full, working WordPress theme: templates, `includes/` (CPTs, services, forms, SEO, admin, hooks), assets, and the bundled country manifest |
| `docs/` | Architecture, contracts, decisions, operations, and quality requirements |
| `prompts/` | AI-assisted content workflows and their acceptance/review criteria |
| `scripts/` | `import-countries.php` — the idempotent country importer |
| `data/` | The canonical, versioned country manifest, its schema documentation, and per-country content files |
| `exports/` | Local/generated export destination; contents ignored by Git |
| `tests/` | PHPUnit coverage for the rotation engine |
| `.github/` | Pull-request template; CI workflows not yet implemented |
| `.vscode/` | Shared, non-personal editor recommendations |

## Coding philosophy

Prefer WordPress core APIs, explicit data contracts, pure date calculations, progressive enhancement, and small composable modules. Store facts once, render on the server, escape at output, sanitize at input, and make time-zone behavior explicit. Content generation and data ingestion must be offline administrative processes, never page-request dependencies.

Architecture decisions belong in `docs/decisions/`. A change that conflicts with an accepted decision requires a superseding decision record.

## Performance goals

- Lighthouse scores above 95 for Performance, Accessibility, Best Practices, and SEO on representative mobile pages.
- Core Web Vitals in the “good” range at the 75th percentile.
- Minimal or zero client JavaScript on content pages; no page builder.
- Responsive images, local/system fonts, small critical CSS, and aggressive public-page caching.
- No remote API calls or schedule mutations during front-end requests.

The enforceable budgets and measurement conditions are in `docs/PERFORMANCE.md`.

## Key documents

- `PROJECT.md` — system architecture, milestones, and roadmap
- `docs/DATA_MODEL.md` — Country content model and field ownership
- `docs/SCHEDULER.md` — weekly sequence and date arithmetic
- `docs/DEPLOYMENT.md` — Windows, VS Code, GitHub, and DreamHost workflow
- `AGENTS.md` — mandatory contributor and AI-agent rules
- `CLAUDE.md` — Claude Code entry-point instructions

