<?php
/**
 * Creates/updates one `mrw_issue` post per JSON file in
 * exports/missionary-review/ (produced by scripts/mrw-fetch-extract.php)
 * and tags each with its detected `mrw_country`/`mrw_person` terms.
 *
 * Run with WP-CLI from the repo root:
 *   wp eval-file scripts/import-missionary-review.php --path=.wp-local/public
 *   wp eval-file scripts/import-missionary-review.php write --path=.wp-local/public
 *
 * `write` is a positional argument (eval-file only exposes $args, not
 * $assoc_args, to the evaluated file — a plain `--write` flag is
 * rejected by WP-CLI itself before the script ever runs). Default is a
 * dry run (reports what would be created/updated, writes nothing) per
 * scripts/README.md's stated policy — pass the `write` argument to
 * actually commit. This mirrors scripts/reconcile-m49.py's dry-run-by-
 * default design rather than scripts/import-countries.php's (which
 * always writes); see docs/decisions/0004-missionary-review-archive-ingestion.md.
 *
 * Idempotent and rename-safe: posts are matched by their frozen
 * `mrw_issue_key` meta (e.g. "mrw-1888-01"), not by title, so
 * re-running after re-extraction updates the same post rather than
 * creating a duplicate — same rationale as `manifest_key` on `country`
 * posts (docs/decisions/0001-deterministic-weekly-schedule.md).
 *
 * @package CountryWeek
 */

if (!defined('WP_CLI') || !WP_CLI) {
    fwrite(STDERR, "This script must be run via WP-CLI: wp eval-file scripts/import-missionary-review.php\n");
    exit(1);
}

$write = in_array('write', $args ?? [], true);

$repo_root = dirname(__DIR__);
$export_dir = $repo_root . '/exports/missionary-review';
$manifest_path = $repo_root . '/theme/country-week/includes/data/country-manifest.json';

$files = glob($export_dir . '/*.json');

if ($files === false || $files === []) {
    WP_CLI::error("No files found in {$export_dir} — run scripts/mrw-fetch-extract.php --write first.");
}

$manifest = json_decode((string) file_get_contents($manifest_path), true);
$country_names = [];

foreach (($manifest['countries'] ?? []) as $country) {
    if (!empty($country['key']) && !empty($country['name'])) {
        $country_names[$country['key']] = $country['name'];
    }
}

WP_CLI::log(sprintf('%s mode: %d issue file(s) found in exports/missionary-review/.', $write ? 'WRITE' : 'DRY RUN', count($files)));

$created = 0;
$updated = 0;
$skipped = 0;
$country_term_ids = [];

foreach ($files as $file) {
    $record = json_decode((string) file_get_contents($file), true);

    if (!is_array($record) || empty($record['key']) || empty($record['year']) || empty($record['kind'])) {
        WP_CLI::warning(sprintf('Skipping %s: missing required fields.', basename($file)));
        $skipped++;

        continue;
    }

    $issue_title = mrw_issue_title($record);
    $post_date = mrw_issue_date($record);
    $existing = find_mrw_post_by_key($record['key']);

    if (!$write) {
        WP_CLI::log(sprintf('%s  %-16s %s', $existing ? 'update' : 'create', $record['key'], $issue_title));

        $existing ? $updated++ : $created++;

        continue;
    }

    if ($existing) {
        $issue_post_id = $existing->ID;
        wp_update_post([
            'ID' => $issue_post_id,
            'post_title' => $issue_title,
            'post_excerpt' => $record['excerpt'] ?? '',
            'post_date' => $post_date,
            'post_date_gmt' => get_gmt_from_date($post_date),
        ]);
        $updated++;
    } else {
        $issue_post_id = wp_insert_post([
            'post_type' => 'mrw_issue',
            'post_title' => $issue_title,
            'post_excerpt' => $record['excerpt'] ?? '',
            'post_status' => 'publish',
            'post_date' => $post_date,
            'post_date_gmt' => get_gmt_from_date($post_date),
        ], true);

        if (is_wp_error($issue_post_id)) {
            WP_CLI::warning(sprintf('Failed to create "%s": %s', $issue_title, $issue_post_id->get_error_message()));
            $skipped++;

            continue;
        }

        $created++;
    }

    update_post_meta($issue_post_id, 'mrw_issue_key', $record['key']);
    update_post_meta($issue_post_id, 'source_pdf_url', $record['source_pdf_url'] ?? '');
    update_post_meta($issue_post_id, 'volume_label', $record['volume_label'] ?? '');
    update_post_meta($issue_post_id, 'issue_kind', $record['kind']);

    $term_ids = [];

    foreach (($record['countries'] ?? []) as $key) {
        if (!isset($country_term_ids[$key])) {
            $name = $country_names[$key] ?? null;

            if ($name === null) {
                continue; // Tag refers to a key no longer in the manifest — skip rather than invent a label.
            }

            $country_term_ids[$key] = resolve_country_term_id($key, $name);
        }

        if ($country_term_ids[$key]) {
            $term_ids[] = $country_term_ids[$key];
        }
    }

    wp_set_object_terms($issue_post_id, $term_ids, 'mrw_country', false);
    wp_set_object_terms($issue_post_id, is_array($record['persons'] ?? null) ? $record['persons'] : [], 'mrw_person', false);
}

WP_CLI::success(sprintf(
    '%s: %d created, %d updated, %d skipped.%s',
    $write ? 'Import complete' : 'Dry run complete',
    $created,
    $updated,
    $skipped,
    $write ? '' : ' Re-run with the `write` argument to apply.'
));

function mrw_issue_title(array $record): string
{
    if ($record['kind'] === 'index') {
        return sprintf('The Missionary Review of the World — %d Index', (int) $record['year']);
    }

    $month_name = $record['month'] ? gmdate('F', mktime(0, 0, 0, (int) $record['month'], 1)) : '';

    return trim(sprintf('The Missionary Review of the World — %s %d', $month_name, (int) $record['year']));
}

/**
 * Issues sort to the 1st of their month; a yearly index sorts to
 * December 31st of that year, so it lands after that year's twelve
 * monthly issues in date order rather than before them.
 */
function mrw_issue_date(array $record): string
{
    if ($record['kind'] === 'index') {
        return sprintf('%04d-12-31 00:00:00', (int) $record['year']);
    }

    return sprintf('%04d-%02d-01 00:00:00', (int) $record['year'], (int) $record['month']);
}

function find_mrw_post_by_key(string $key): ?WP_Post
{
    $posts = get_posts([
        'post_type' => 'mrw_issue',
        'post_status' => 'any',
        'posts_per_page' => 1,
        'no_found_rows' => true,
        'meta_key' => 'mrw_issue_key',
        'meta_value' => $key,
    ]);

    return $posts[0] ?? null;
}

/**
 * Ensures an `mrw_country` term exists with slug === the manifest key
 * (not an autogenerated slug from the name), so it can be reliably
 * cross-linked to the matching `country` post by key. Cached per run
 * since the same ~196 countries repeat across hundreds of issues.
 */
function resolve_country_term_id(string $key, string $name): int
{
    $term = get_term_by('slug', $key, 'mrw_country');

    if ($term instanceof WP_Term) {
        return $term->term_id;
    }

    $result = wp_insert_term($name, 'mrw_country', ['slug' => $key]);

    if (is_wp_error($result)) {
        WP_CLI::warning(sprintf('Could not create mrw_country term "%s" (%s): %s', $name, $key, $result->get_error_message()));

        return 0;
    }

    return (int) $result['term_id'];
}
