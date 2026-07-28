# Deployment Workflow

> **Implementation status (2026-07-28):** CI and the DreamHost release
> pipeline described below are now real, working GitHub Actions workflows
> (`.github/workflows/ci.yml` and `.github/workflows/deploy.yml`). What's
> still aspirational: a genuinely separate DreamHost *staging* environment
> (this pipeline deploys straight to production behind a manual approval
> gate — see "Release procedure" below), automated pruning of old release
> directories (old releases accumulate until someone cleans them up by
> hand), and database/content migration tooling (deploys are code-only;
> getting the live database's content to match `data/*.json` is a
> separate, deliberately manual action — see "Content and media" below).
> WordPress Coding Standards checks run in CI but are currently
> report-only, not a merge gate — see `phpcs.xml.dist`'s own docblock for
> why, and for the pre-existing findings still outstanding.
>
> Local dev remains the git-ignored `.wp-local/` environment (documented
> in `docs/SETUP.md`) using PHP's built-in server and the SQLite database
> integration plugin instead of Local/DDEV/MySQL. That stack is for
> development/verification only; it is not a deployment target and
> nothing about it is assumed by the theme itself (the theme has no
> SQLite-specific code — the drop-in is transparent to WordPress).

## Recommended topology

Use three environments: local Windows development, DreamHost staging, and DreamHost production. GitHub is the source of reviewed code and release artifacts. Databases and uploads are environment data, not deployment artifacts.

## Windows and VS Code

- Use Git for Windows with consistent LF line endings governed by `.gitattributes` when introduced.
- Use Local or DDEV for an isolated WordPress environment. Pick one during Phase 2 and document exact versions.
- Open the repository root in VS Code, not the WordPress core directory.
- Link the theme directory into the local site where practical so edits remain in one working tree.
- Keep credentials in the host’s secret store or untracked local environment configuration.

## Git and GitHub

- Protect `main`; require pull requests, passing checks, and at least one review.
- Use short-lived `feature/`, `fix/`, `docs/`, and `chore/` branches.
- **Implemented** (`.github/workflows/ci.yml`): PHP syntax checks (`php -l` across `theme/country-week` and `scripts`), manifest/schema validation (`scripts/validate-manifest.php`), the PHPUnit suite, and WordPress Coding Standards (`phpcs.xml.dist` — currently report-only, see its docblock).
- **Not implemented**: JavaScript/CSS linting (no JS/CSS lint tooling exists in the repo yet), dependency/secret scanning.
- Tag releases with semantic versions for the deployable theme, such as `v0.2.0` — pushing a `v*` tag is what triggers `.github/workflows/deploy.yml`.
- Build the artifact in CI from a clean checkout, runtime files only, via `scripts/build-release.sh` (allow-lists `theme/country-week`'s actual runtime paths rather than blacklisting — see the script's own comments).

## DreamHost

Confirmed: hosting is DreamHost, with SSH/SFTP access. Keep the release directory path, SSH user/host, and all credentials in GitHub Environment secrets (the `production` environment — see "Required secrets" below), never in workflow files.

Release layout (`deploy.yml` establishes and relies on this exact shape):

```text
wp-content/themes/
  country-week -> country-week-releases/v0.3.0/   (DREAMHOST_THEME_LINK, a symlink)
  country-week-releases/
    v0.3.0/                                        (DREAMHOST_RELEASES_PATH/v0.3.0)
    v0.2.0/
```

**One-time DreamHost-side setup, before the first real deploy** (nothing here can be done by CI — this is manual, one-time host configuration):

1. Create a restricted SSH deploy user/key pair on DreamHost; do not reuse a personal login.
2. If `wp-content/themes/country-week` isn't already a symlink, convert it by hand once: copy the current live theme into `country-week-releases/<some-baseline-name>/`, delete the original `country-week` directory, then create the symlink pointing at that baseline directory. Do this during low traffic — it's the one step in this whole pipeline that isn't atomic-by-construction.
3. Confirm `wp` (WP-CLI) is available on the host and note its path/invocation if it needs one (the remote smoke-check step tries it but skips gracefully if it's missing).

Upload always goes to a new release directory; promotion is always the atomic symlink swap (`ln -sfn`) `deploy.yml` performs — never a file-by-file overwrite of the live directory.

## Release procedure (as implemented in `.github/workflows/deploy.yml`)

1. Push a `v*` tag (or run the workflow manually via `workflow_dispatch` against a tag).
2. The `verify` job re-runs `ci.yml`'s checks (syntax, manifest validation, PHPUnit, WPCS-report) against that exact tagged commit.
3. The `build` job runs `scripts/build-release.sh` and uploads the result as a build artifact.
4. The `promote` job runs behind the GitHub `production` Environment's required-reviewer approval gate — this is the "explicit authorization" step. Once approved: `rsync`s the release to a new `DREAMHOST_RELEASES_PATH/<tag>/` directory, runs remote smoke checks (`php -l` on every uploaded file, `wp theme status` if available), captures the current symlink target for rollback, then atomically re-points `DREAMHOST_THEME_LINK` at the new release.
5. HTTP smoke checks run against the live `SITE_URL` (homepage, a country page, `robots.txt`). If any fail, the workflow automatically re-points the symlink back to the release it captured in step 4 and fails loudly — no silent partial promotion.
6. There is currently no separate staging environment or database-migration step — deploys are code-only (see "Content and media" below) and go straight to production behind the approval gate. A true staging environment is future work, not yet provisioned.

## Rollback

`deploy.yml` supports two forms:

- **Automatic**: if the post-promotion HTTP smoke checks fail, the workflow itself re-points the symlink back to whatever it was before promotion, in the same run.
- **Manual**: run the `Deploy to DreamHost` workflow via `workflow_dispatch` with `rollback_to` set to an existing release folder name (e.g. `v0.2.0`) already present under `DREAMHOST_RELEASES_PATH`. This skips build/upload and just re-points the symlink, then re-runs the HTTP smoke checks.

Old release directories are **not** pruned automatically — that's a manual housekeeping task for now (retain at least the previous two, per the original plan here, until automated pruning is built).

## Content and media

Do not automatically copy the production database down without privacy review. Promote configuration/code through Git; author content in the intended editorial environment. Use a deliberate, URL-safe media migration method when staging content must move to production. Never deploy `uploads` via Git.

This pipeline deploys **code only**. It never runs `scripts/import-countries.php` or otherwise writes to the production database — getting the live site's content (posts, post meta, taxonomy terms) to match what's in `data/*.json` is a separate, explicitly-authorized action, run by hand via WP-CLI over SSH against the production database, done only after a code deploy has already landed successfully.

## Secrets

Set these in the GitHub `production` Environment (Settings → Environments → `production`), with at least one required reviewer configured — that reviewer gate is what makes the `promote` and `rollback` jobs in `deploy.yml` require explicit human authorization before touching the live host. Never put any of these in a workflow file.

| Secret | Purpose |
| --- | --- |
| `DREAMHOST_HOST` | SSH hostname for the DreamHost server. |
| `DREAMHOST_USER` | Restricted SSH deploy user (not a personal login). |
| `DREAMHOST_SSH_KEY` | Private key for that user, in a format `ssh -i` accepts. |
| `DREAMHOST_HOST_KEY` | Pinned host key fingerprint(s), in `known_hosts` format (e.g. from `ssh-keyscan`) — never `StrictHostKeyChecking=no`. |
| `DREAMHOST_RELEASES_PATH` | Absolute path to the releases directory, e.g. `/home/user/example.com/wp-content/themes/country-week-releases`. |
| `DREAMHOST_THEME_LINK` | Absolute path to the `country-week` symlink itself that `deploy.yml` re-points on every promotion/rollback. |
| `DREAMHOST_WP_PATH` | `--path` for the remote `wp theme status` smoke check (WP-CLI's WordPress root). |
| `SITE_URL` | Public site origin used for post-promotion HTTP smoke checks, e.g. `https://thecountryoftheweek.com`. |

Rotate `DREAMHOST_SSH_KEY` periodically, and immediately if anyone with repo access changes. `deploy.yml` never echoes any of these values.

