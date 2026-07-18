# Deployment Workflow

> **Implementation status (2026-07-15):** Not yet built. Everything below
> is still the plan, not a description of working infrastructure. What
> exists today: a git-ignored local verification environment
> (`.wp-local/`, documented in `docs/SETUP.md`) using PHP's built-in
> server and the SQLite database integration plugin instead of
> Local/DDEV/MySQL — chosen because this environment had no Docker or
> MySQL available and needed a zero-admin-rights path to a working
> WordPress install. That local stack is for development/verification
> only; it is not a deployment target and nothing about it is assumed by
> the theme itself (the theme has no SQLite-specific code — the drop-in
> is transparent to WordPress). CI, the DreamHost release pipeline,
> staging, and rollback procedures described below have not been
> implemented. Confirm actual hosting details (DreamHost or otherwise)
> before building this out.

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
- Require PHP syntax/style checks, WordPress Coding Standards, JavaScript/CSS linting when applicable, unit/integration tests, and manifest/schema validation.
- Enable dependency and secret scanning when dependencies are introduced.
- Tag releases with semantic versions for the deployable theme, such as `v0.2.0`.
- Build the artifact in CI from a clean checkout. Include only runtime theme files and production dependencies.

## DreamHost

Confirm the current DreamHost PHP, SSH/SFTP, cron, staging, backup, and WP-CLI capabilities before implementation; hosting features change. Keep document root, SSH user, and secrets in GitHub environment secrets, not workflow files.

Recommended release layout when SSH access permits:

```text
wp-content/themes/
  country-week -> country-week-releases/2026.07.19.1
  country-week-releases/
    2026.07.19.1/
```

Upload to a new release directory, validate it, then atomically switch the active symlink. If DreamHost’s plan or filesystem does not permit symlinks, upload to a temporary directory and use a carefully tested rename strategy. Never overwrite the live theme file-by-file if atomic promotion is available.

## Release procedure

1. Merge a reviewed pull request and create a release tag.
2. CI runs all checks and creates a checksummed theme ZIP.
3. Deploy that artifact to staging using SSH/SFTP with host-key verification.
4. Run PHP syntax checks, WP-CLI theme status checks, schedule smoke tests, and HTTP checks on staging.
5. Obtain production approval through a protected GitHub environment.
6. Back up the database before any migration; code-only releases do not require database replacement.
7. Upload the exact staging-tested artifact to a new production release directory.
8. Put schema/data migrations into a documented maintenance transaction where possible.
9. Promote atomically, clear/warm caches, and test homepage, a Country page, archive, robots/sitemap, and expected featured key.
10. Record release, artifact checksum, migration status, and operator.

## Rollback

Retain at least the previous two theme releases. For a code-only failure, switch back to the previous artifact and purge caches. Database migrations require a tested backward plan; if irreversible, restore the pre-deploy database backup and matching code release. Practice rollback on staging before launch.

## Content and media

Do not automatically copy the production database down without privacy review. Promote configuration/code through Git; author content in the intended editorial environment. Use a deliberate, URL-safe media migration method when staging content must move to production. Never deploy `uploads` via Git.

## Secrets

Use GitHub environment secrets for deployment credentials and DreamHost configuration/environment variables for application secrets. Prefer a restricted deployment user and key, limit it to necessary paths, verify host keys, rotate credentials, and never print secrets in logs.

