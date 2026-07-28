#!/usr/bin/env bash
#
# Builds a clean, versioned release artifact of the theme's runtime files
# only — matches docs/DEPLOYMENT.md's "Build the artifact in CI from a
# clean checkout. Include only runtime theme files." Explicitly
# allow-lists what goes in (theme/country-week's actual runtime paths)
# rather than blacklisting what to skip, so nothing accidental (a stray
# scratch file, an editor swap file that somehow got tracked) ever rides
# along silently.
#
# Usage:
#   scripts/build-release.sh <version>
#
# <version> becomes the release directory/zip name, e.g. v0.3.0 or a git
# short SHA for a manual test run. Output goes to dist/country-week-<version>/
# and dist/country-week-<version>.zip, plus a .sha256 checksum file
# alongside the zip. dist/ is already git-ignored.

set -euo pipefail

if [[ $# -ne 1 || -z "$1" ]]; then
    echo "Usage: $0 <version>" >&2
    exit 1
fi

version="$1"
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
theme_src="$repo_root/theme/country-week"
dist_dir="$repo_root/dist"
release_name="country-week-${version}"
release_dir="$dist_dir/$release_name"
zip_path="$dist_dir/${release_name}.zip"

if [[ ! -d "$theme_src" ]]; then
    echo "FATAL: theme source not found at $theme_src" >&2
    exit 1
fi

rm -rf "$release_dir" "$zip_path" "${zip_path}.sha256"
mkdir -p "$release_dir"

# Runtime paths only. style.css is required for WordPress to recognize
# the theme at all; README.md and any future dev-only docs are
# deliberately left out of the deployed artifact.
runtime_top_level_files=(
    404.php
    archive-country.php
    footer.php
    front-page.php
    functions.php
    header.php
    index.php
    page-adopt-a-country.php
    page-email-preferences.php
    page-join-us-in-prayer.php
    page-login.php
    page-register.php
    page-schedule.php
    page-suggest-an-edit.php
    page.php
    search.php
    searchform.php
    single-country.php
    style.css
)
runtime_dirs=(
    assets
    includes
    templates
)

for file in "${runtime_top_level_files[@]}"; do
    if [[ ! -f "$theme_src/$file" ]]; then
        echo "FATAL: expected runtime file missing: theme/country-week/$file" >&2
        echo "(build-release.sh's allow-list is out of sync with the theme — update both together)" >&2
        exit 1
    fi

    cp "$theme_src/$file" "$release_dir/$file"
done

for dir in "${runtime_dirs[@]}"; do
    if [[ ! -d "$theme_src/$dir" ]]; then
        echo "FATAL: expected runtime directory missing: theme/country-week/$dir" >&2
        exit 1
    fi

    cp -R "$theme_src/$dir" "$release_dir/$dir"
done

(cd "$dist_dir" && zip -rq "${release_name}.zip" "$release_name")
(cd "$dist_dir" && sha256sum "${release_name}.zip" > "${release_name}.zip.sha256")

file_count=$(find "$release_dir" -type f | wc -l | tr -d ' ')
echo "Built $zip_path ($file_count files) from theme/country-week"
