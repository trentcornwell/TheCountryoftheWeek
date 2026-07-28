<?php
/**
 * Validates data/country-index.json and its per-country content files
 * without any WordPress bootstrap — same "pure PHP, clock/IO isolated at
 * the edges" spirit as includes/services/class-rotation-service.php, and
 * the same dry-run-first safety posture as reconcile-m49.py. Run as part
 * of CI (see .github/workflows/ci.yml) and locally before importing:
 *
 *   php scripts/validate-manifest.php
 *
 * Exits non-zero and prints every problem found (not just the first) on
 * any violation. Checks nothing that scripts/import-countries.php doesn't
 * already implicitly assume — this just fails loudly and early instead of
 * partway through an import.
 *
 * @package CountryWeek
 */

declare(strict_types=1);

$repo_root = dirname(__DIR__);
$data_dir = $repo_root . '/data';
$manifest_path = $data_dir . '/country-index.json';

$errors = [];

/**
 * @param mixed $condition
 */
function record_if(array &$errors, $condition, string $message): void
{
    if ($condition) {
        $errors[] = $message;
    }
}

if (!file_exists($manifest_path)) {
    fwrite(STDERR, "FATAL: manifest not found at {$manifest_path}\n");
    exit(1);
}

$manifest_raw = file_get_contents($manifest_path);
$manifest = json_decode($manifest_raw, true);

if (!is_array($manifest) || json_last_error() !== JSON_ERROR_NONE) {
    fwrite(STDERR, 'FATAL: country-index.json is not valid JSON: ' . json_last_error_msg() . "\n");
    exit(1);
}

$allowed_continents = ['Africa', 'Americas', 'Asia', 'Europe', 'Oceania'];

foreach (['manifest_version', 'anchor', 'countries'] as $required_top_level_field) {
    record_if($errors, !array_key_exists($required_top_level_field, $manifest), "manifest is missing required top-level field '{$required_top_level_field}'");
}

if (!isset($manifest['countries']) || !is_array($manifest['countries'])) {
    fwrite(STDERR, "FATAL: manifest has no usable 'countries' array; cannot continue.\n");
    exit(1);
}

$anchor = $manifest['anchor'] ?? [];

foreach (['country_key', 'local_date', 'time', 'timezone'] as $anchor_field) {
    record_if($errors, !isset($anchor[$anchor_field]) || $anchor[$anchor_field] === '', "anchor is missing required field '{$anchor_field}'");
}

$seen_keys = [];
$seen_names = [];

foreach ($manifest['countries'] as $index => $entry) {
    $label = is_array($entry) && isset($entry['key']) ? $entry['key'] : "index {$index}";

    if (!is_array($entry)) {
        $errors[] = "countries[{$index}] is not an object";
        continue;
    }

    foreach (['key', 'name', 'continent', 'region'] as $field) {
        record_if($errors, !isset($entry[$field]) || $entry[$field] === '', "{$label}: missing required field '{$field}'");
    }

    if (isset($entry['key'])) {
        $key = $entry['key'];

        record_if($errors, !is_string($key) || preg_match('/^[a-z0-9-]+$/', $key) !== 1, "{$label}: key '{$key}' must be lowercase letters, digits, and hyphens only");

        if (isset($seen_keys[$key])) {
            $errors[] = "{$label}: duplicate key '{$key}' (also used by countries[{$seen_keys[$key]}])";
        }

        $seen_keys[$key] = $index;
    }

    if (isset($entry['name'])) {
        $name = $entry['name'];

        if (isset($seen_names[$name])) {
            $errors[] = "{$label}: duplicate name '{$name}' (also used by countries[{$seen_names[$name]}])";
        }

        $seen_names[$name] = $index;
    }

    if (isset($entry['continent'])) {
        record_if($errors, !in_array($entry['continent'], $allowed_continents, true), "{$label}: continent '{$entry['continent']}' is not one of the UN M49 regions (" . implode(', ', $allowed_continents) . ')');
    }
}

if (isset($anchor['country_key']) && !isset($seen_keys[$anchor['country_key']])) {
    $errors[] = "anchor.country_key '{$anchor['country_key']}' does not match any country in the manifest";
}

$required_content_groups = ['quick_facts', 'summaries', 'facts_and_lists', 'prayer_and_mission'];
$required_prayer_fields = ['prayer_intro', 'prayer_points', 'mission_emphasis', 'prayer_source'];

foreach ($seen_keys as $key => $index) {
    $content_path = $data_dir . "/{$key}.json";

    if (!file_exists($content_path)) {
        $errors[] = "{$key}: no matching content file at data/{$key}.json";
        continue;
    }

    $content = json_decode((string) file_get_contents($content_path), true);

    if (!is_array($content) || json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = "{$key}: data/{$key}.json is not valid JSON: " . json_last_error_msg();
        continue;
    }

    $manifest_name = $manifest['countries'][$index]['name'] ?? null;

    record_if($errors, ($content['name'] ?? null) !== $manifest_name, "{$key}: data/{$key}.json name '" . ($content['name'] ?? '(missing)') . "' does not match manifest name '{$manifest_name}'");

    record_if($errors, !isset($content['excerpt']) || !is_string($content['excerpt']) || trim($content['excerpt']) === '', "{$key}: data/{$key}.json is missing a non-empty 'excerpt'");

    foreach ($required_content_groups as $group) {
        record_if($errors, !isset($content[$group]) || !is_array($content[$group]), "{$key}: data/{$key}.json is missing content group '{$group}'");
    }

    if (isset($content['prayer_and_mission']) && is_array($content['prayer_and_mission'])) {
        foreach ($required_prayer_fields as $prayer_field) {
            record_if($errors, !array_key_exists($prayer_field, $content['prayer_and_mission']), "{$key}: prayer_and_mission is missing field '{$prayer_field}'");
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, count($errors) . " manifest validation error(s):\n");

    foreach ($errors as $error) {
        fwrite(STDERR, "  - {$error}\n");
    }

    exit(1);
}

$country_count = count($manifest['countries']);
echo "OK: {$country_count} countries validated in country-index.json, all with matching content files.\n";
