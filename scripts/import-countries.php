<?php
/**
 * Creates/updates every Country post from the canonical manifest
 * (data/country-index.json), then layers on full content from any
 * data/*.json country content files (currently just data/kiribati.json).
 *
 * Run with WP-CLI from the repo root:
 *   wp eval-file scripts/import-countries.php --path=.wp-local/public
 *
 * Idempotent and rename-safe: posts are matched by their frozen
 * `manifest_key` meta (see Services\Country_Manifest), not by title,
 * so re-running this after an editor has corrected a country's title
 * updates that same post rather than creating a duplicate or shifting
 * the rotation — see docs/decisions/0001-deterministic-weekly-schedule.md.
 *
 * Also copies data/country-index.json into the theme's bundled
 * includes/data/country-manifest.json, and data/schedule-overrides.json
 * into includes/data/schedule-overrides.json, since the theme must ship
 * its own copy of both to resolve rotation order and any active
 * schedule overrides at runtime without depending on anything outside
 * theme/country-week/.
 *
 * Also imports data/one-off-features.json — Country posts for one-off
 * featured weeks (see docs/decisions/0006-temporary-schedule-overrides.md)
 * that are deliberately NOT part of data/country-index.json and so never
 * participate in the perpetual rotation itself; they only ever surface
 * via a matching entry in data/schedule-overrides.json.
 *
 * @package CountryWeek
 */

if (!defined('WP_CLI') || !WP_CLI) {
    fwrite(STDERR, "This script must be run via WP-CLI: wp eval-file scripts/import-countries.php\n");
    exit(1);
}

$repo_root = dirname(__DIR__);
$data_dir = $repo_root . '/data';
$manifest_source = $data_dir . '/country-index.json';
$manifest_bundled = $repo_root . '/theme/country-week/includes/data/country-manifest.json';
$overrides_source = $data_dir . '/schedule-overrides.json';
$overrides_bundled = $repo_root . '/theme/country-week/includes/data/schedule-overrides.json';

if (!copy($manifest_source, $manifest_bundled)) {
    WP_CLI::error('Could not copy the manifest into the theme bundle.');
}

WP_CLI::log('Synced data/country-index.json to theme/country-week/includes/data/country-manifest.json.');

if (file_exists($overrides_source)) {
    if (!copy($overrides_source, $overrides_bundled)) {
        WP_CLI::error('Could not copy schedule-overrides.json into the theme bundle.');
    }

    WP_CLI::log('Synced data/schedule-overrides.json to theme/country-week/includes/data/schedule-overrides.json.');
}

$index = json_decode((string) file_get_contents($manifest_source), true);

if (!is_array($index) || empty($index['countries'])) {
    WP_CLI::error('Could not read data/country-index.json');
}

WP_CLI::log(sprintf('Importing %d countries (manifest v%d)...', count($index['countries']), (int) ($index['manifest_version'] ?? 0)));

$created = 0;
$updated = 0;

foreach ($index['countries'] as $entry) {
    [$created, $updated] = import_country_stub($entry, $created, $updated);
}

WP_CLI::success(sprintf('Countries: %d created, %d already existed (updated).', $created, $updated));

// One-off, non-manifest posts (see the file docblock above). Imported
// with the exact same idempotent, rename-safe logic as manifest
// countries, just from a separate file that country-index.json/
// Country_Manifest never reads, so these can never affect rotation
// order, count, or any other country's schedule.
$one_off_source = $data_dir . '/one-off-features.json';

if (file_exists($one_off_source)) {
    $one_off = json_decode((string) file_get_contents($one_off_source), true);

    if (is_array($one_off) && !empty($one_off['countries'])) {
        $one_off_created = 0;
        $one_off_updated = 0;

        foreach ($one_off['countries'] as $entry) {
            [$one_off_created, $one_off_updated] = import_country_stub($entry, $one_off_created, $one_off_updated);
        }

        WP_CLI::success(sprintf('One-off features: %d created, %d already existed (updated).', $one_off_created, $one_off_updated));
    }
}

/**
 * Create or update one Country post from a manifest-shaped entry
 * (key/name/continent/region), matched idempotently by manifest_key
 * (falling back to title for a one-time pre-manifest-key migration).
 * Shared by both the country-index.json loop and the
 * one-off-features.json loop above — used to create posts, but never to
 * decide rotation order, which comes solely from Country_Manifest.
 *
 * @return array{0:int,1:int} updated [$created, $updated] counters.
 */
function import_country_stub(array $entry, int $created, int $updated): array
{
    $manifest_key = $entry['key'];
    $name = $entry['name'];

    $existing = find_country_post_by_manifest_key($manifest_key);

    if (!$existing) {
        $existing = find_country_post_by_title($name);
    }

    if ($existing) {
        $post_id = $existing->ID;
        $updated++;
    } else {
        $post_id = wp_insert_post([
            'post_type' => 'country',
            'post_title' => $name,
            'post_status' => 'publish',
        ], true);

        if (is_wp_error($post_id)) {
            WP_CLI::warning(sprintf('Failed to create "%s": %s', $name, $post_id->get_error_message()));

            return [$created, $updated];
        }

        $created++;
    }

    update_post_meta($post_id, 'manifest_key', $manifest_key);

    if (!empty($entry['continent'])) {
        wp_set_object_terms($post_id, $entry['continent'], 'continent', false);
    }

    if (!empty($entry['region'])) {
        wp_set_object_terms($post_id, $entry['region'], 'region', false);
    }

    return [$created, $updated];
}

// Layer on full content files. Any data/*.json file other than
// country-index.json and countries.schema.json is treated as a
// per-country content file, matched to a post via its "name" field
// (slugified into the same manifest key format). A martinique.json or
// mayotte.json added later will be picked up here exactly like any
// other country's content file — no further script changes needed.
$content_files = glob($data_dir . '/*.json');
$skip = ['country-index.json', 'countries.schema.json', 'schedule-overrides.json', 'one-off-features.json'];

foreach ($content_files as $file) {
    $filename = basename($file);

    if (in_array($filename, $skip, true)) {
        continue;
    }

    $content = json_decode((string) file_get_contents($file), true);

    if (!is_array($content) || empty($content['name'])) {
        WP_CLI::warning(sprintf('Skipping %s: missing "name" field.', $filename));

        continue;
    }

    $post = find_country_post_by_manifest_key(sanitize_title($content['name']));

    if (!$post) {
        $post = find_country_post_by_title($content['name']);
    }

    if (!$post) {
        WP_CLI::warning(sprintf('Skipping %s: no Country post titled "%s" found.', $filename, $content['name']));

        continue;
    }

    import_country_content($post->ID, $content);

    WP_CLI::success(sprintf('Applied full content from %s to "%s".', $filename, $content['name']));
}

function find_country_post_by_manifest_key(string $manifest_key): ?WP_Post
{
    $posts = get_posts([
        'post_type' => 'country',
        'post_status' => 'any',
        'posts_per_page' => 1,
        'no_found_rows' => true,
        'meta_key' => 'manifest_key',
        'meta_value' => $manifest_key,
    ]);

    return $posts[0] ?? null;
}

/**
 * Exact title match within the Country post type. Used instead of the
 * deprecated get_page_by_title() (deprecated since WP 6.2), and only
 * as a one-time migration fallback for posts that predate manifest keys.
 */
function find_country_post_by_title(string $title): ?WP_Post
{
    $posts = get_posts([
        'post_type' => 'country',
        'title' => $title,
        'post_status' => 'any',
        'posts_per_page' => 1,
        'no_found_rows' => true,
    ]);

    return $posts[0] ?? null;
}

/**
 * Flatten a country content file's grouped structure into meta_key =>
 * value pairs and save them, converting list arrays to the
 * newline-delimited format Country_Meta_Fields::lines() expects.
 */
function import_country_content(int $post_id, array $content): void
{
    if (!empty($content['excerpt'])) {
        wp_update_post(['ID' => $post_id, 'post_excerpt' => $content['excerpt']]);
    }

    $groups = ['quick_facts', 'summaries', 'facts_and_lists', 'prayer_and_mission'];

    foreach ($groups as $group) {
        if (empty($content[$group]) || !is_array($content[$group])) {
            continue;
        }

        foreach ($content[$group] as $key => $value) {
            $stored_value = is_array($value) ? implode("\n", $value) : $value;
            update_post_meta($post_id, $key, $stored_value);
        }
    }
}
