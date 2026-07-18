# Developer Setup Guide

This documents the exact local environment used to build and verify this
theme — no Docker, no MySQL, no admin rights required. `docs/DEPLOYMENT.md`
originally suggested Local/DDEV; those remain fine alternatives if you have
them, but they're not required.

## Why this approach

The theme's own runtime has zero dependency on SQLite — it just talks to
`$wpdb` like any WordPress theme. SQLite is only used here to avoid standing
up a MySQL server for local verification. If your environment already has
Docker or a MySQL-based stack (Local, DDEV, MAMP, etc.), skip straight to
["Any WordPress environment"](#any-wordpress-environment) below.

## Zero-Docker local environment (Windows)

### 1. Install PHP

```powershell
winget install --id PHP.PHP.8.3 -e --source winget --accept-source-agreements --accept-package-agreements --scope user --silent
```

This installs PHP with no admin rights required. After it finishes, open a
**new** shell (PATH is updated on new shells only), or reference the binary
directly:

```
%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe
```

Copy `php.ini-development` to `php.ini` next to `php.exe` and uncomment
(remove the leading `;` from) these lines, plus set `extension_dir`:

```ini
extension_dir = "ext"
extension=curl
extension=fileinfo
extension=gd
extension=mbstring
extension=openssl
extension=pdo_sqlite
extension=sockets
extension=zip
```

### 2. Download WordPress core, WP-CLI, and PHPUnit

From the repo root:

```bash
mkdir -p .wp-local
cd .wp-local
curl -fsSL -o wordpress.zip https://wordpress.org/latest.zip
curl -fsSL -o wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
curl -fsSL -o phpunit.phar https://phar.phpunit.de/phpunit-10.phar
unzip -q wordpress.zip && mv wordpress public
```

(`.wp-local/` is git-ignored — see `.gitignore`.)

### 3. Install the SQLite database drop-in

No MySQL server needed:

```bash
curl -fsSL -o .wp-local/sqlite-plugin.zip "https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip"
mkdir -p .wp-local/public/wp-content/plugins
cd .wp-local/public/wp-content/plugins && unzip -qo ../../../sqlite-plugin.zip
cp sqlite-database-integration/db.copy ../db.php
```

### 4. Create `.wp-local/public/wp-config.php`

Standard WordPress config, plus two things specific to this setup:

```php
// Populates $wp_theme_directories directly, since register_theme_directory()
// isn't loaded yet at this point in bootstrap. This lets WordPress serve the
// theme straight from the repo's theme/ folder — no copying or symlinking,
// so edits are live immediately.
global $wp_theme_directories;
$wp_theme_directories[] = dirname(__DIR__, 2) . '/theme';
```

The `DB_*` constants can be any placeholder values — the SQLite drop-in
(`wp-content/db.php`) intercepts `$wpdb` before they'd matter.

### 5. Install WordPress and activate the theme

```bash
PHP=/path/to/php.exe
WP="$PHP .wp-local/wp-cli.phar --path=.wp-local/public"

$WP core install --url="http://localhost:8080" --title="The Country of the Week" \
  --admin_user=admin --admin_password=admin --admin_email=you@example.com --skip-email

$WP theme activate country-week
$WP rewrite structure '/%postname%/' --hard
$WP rewrite flush --hard
```

### 6. Import all countries

```bash
$WP eval-file scripts/import-countries.php
```

This creates all 196 country posts (Kiribati with full content, the rest as
stubs) and syncs `data/country-index.json` into the theme's bundled
`includes/data/country-manifest.json`. Safe to re-run any time the data
files change — see `docs/CONTENT-GUIDE.md`.

### 7. Serve it

Use a router script rather than a bare `php -S` — without one, PHP's
built-in server treats any URL with a recognized extension (like
`.xml`) as a literal file lookup instead of falling through to
WordPress's front controller, which produces false 404s (e.g.
`/wp-sitemap.xml`) that have nothing to do with the theme:

```bash
# .wp-local/router.php
cat > .wp-local/router.php <<'EOF'
<?php
$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . '/public' . $path;
if ($path !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}
chdir(__DIR__ . '/public');
require __DIR__ . '/public/index.php';
EOF

"$PHP" -S localhost:8080 -t .wp-local/public .wp-local/router.php
```

The `-t .wp-local/public` is required, not optional — without an explicit docroot matching where the router resolves file paths, any request that maps to a real file (`admin-post.php`, `wp-login.php`, etc.) 404s: the router correctly returns `false` to let the built-in server handle it directly, but the server then looks for that file relative to *its own* docroot (the directory `php -S` was launched from), not `.wp-local/public`, unless `-t` says otherwise.

Visit `http://localhost:8080/`.

## Any WordPress environment

If you already have a working local WordPress site (Local, DDEV, MAMP,
XAMPP, etc.):

1. Symlink or copy `theme/country-week` into `wp-content/themes/`.
2. Activate **The Country of the Week**.
3. Set **Settings → General → Timezone** to `America/New_York`.
4. Set permalinks to "Post name" (Settings → Permalinks).
5. Run the importer with your environment's WP-CLI: `wp eval-file scripts/import-countries.php`.

## Running tests

The rotation engine's tests don't need WordPress at all:

```bash
php .wp-local/phpunit.phar -c phpunit.xml.dist
```

or, with Composer available:

```bash
composer install
vendor/bin/phpunit
```

## Verifying the theme end to end

With the local server running:

```bash
curl -s http://localhost:8080/                          # homepage (countdown until 2026-07-19, then the active country)
curl -s http://localhost:8080/countries/                 # archive with search/continent/A-Z filters
curl -s http://localhost:8080/countries/kiribati/         # single country page
curl -s http://localhost:8080/countries/kiribati/print/   # printable sheet with QR code
```

Check `.wp-local/public/wp-content/debug.log` for PHP warnings/notices —
`WP_DEBUG_LOG` is enabled by the `wp-config.php` above.
