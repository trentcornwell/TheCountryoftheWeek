<?php
/**
 * Downloads and attaches map and flag images to each Country post.
 * Maps come from github.com/factbook/media (CC0, CIA World Factbook
 * lineage). Flags come from github.com/hampusborgos/country-flags
 * (public domain, sourced from Wikimedia Commons, 1000px-wide renders
 * — much higher resolution than factbook/media's ~150px flags, which
 * looked blurry on the presentation slides).
 *
 * Run with WP-CLI from the repo root:
 *   wp eval-file scripts/import-media.php --path=.wp-local/public
 *
 * Maps are idempotent (skipped if already attached). Flags are always
 * re-fetched and replace any existing flag attachment — this script
 * was updated once to upgrade flag resolution, and re-running it
 * should not silently skip that upgrade for countries imported before
 * the switch.
 *
 * @package CountryWeek
 */

if (!defined('WP_CLI') || !WP_CLI) {
    fwrite(STDERR, "This script must be run via WP-CLI: wp eval-file scripts/import-media.php\n");
    exit(1);
}

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$repo_root = dirname(__DIR__);
$codes_file = json_decode((string) file_get_contents($repo_root . '/data/factbook-media-codes.json'), true);
$fips_codes = $codes_file['codes'] ?? [];
$iso_codes = $codes_file['iso_codes'] ?? [];

if (empty($fips_codes) || empty($iso_codes)) {
    WP_CLI::error('Could not read data/factbook-media-codes.json (need both codes and iso_codes).');
}

const MAP_BASE = 'https://raw.githubusercontent.com/factbook/media/master/maps/';
const FLAG_BASE = 'https://cdn.jsdelivr.net/npm/svg-country-flags@1.2.10/png1000px/';

$attached = ['map' => 0, 'flag' => 0];
$skipped = ['map' => 0];
$failed = [];

foreach ($fips_codes as $key => $fips_code) {
    $posts = get_posts([
        'post_type' => 'country',
        'post_status' => 'any',
        'posts_per_page' => 1,
        'no_found_rows' => true,
        'meta_key' => 'manifest_key',
        'meta_value' => $key,
    ]);

    if (empty($posts)) {
        continue;
    }

    $post = $posts[0];

    // Map: idempotent, skip if already attached.
    $existing_map = (int) get_post_meta($post->ID, 'map_image_id', true);

    if ($existing_map && get_post($existing_map)) {
        $skipped['map']++;
    } else {
        $url = MAP_BASE . $fips_code . '.png';
        $attachment_id = sideload_image_to_post($url, $post->ID, get_the_title($post) . ' Map');

        if (is_wp_error($attachment_id)) {
            $failed[] = "$key (map): " . $attachment_id->get_error_message();
        } else {
            update_post_meta($post->ID, 'map_image_id', $attachment_id);
            $attached['map']++;
        }
    }

    // Flag: always refresh to the high-resolution source.
    $iso_code = $iso_codes[$key] ?? null;

    if (!$iso_code) {
        $failed[] = "$key (flag): no ISO code mapped";

        continue;
    }

    $old_flag_id = (int) get_post_meta($post->ID, 'flag_image_id', true);

    $url = FLAG_BASE . strtolower($iso_code) . '.png';
    $attachment_id = sideload_image_to_post($url, $post->ID, get_the_title($post) . ' Flag');

    if (is_wp_error($attachment_id)) {
        $failed[] = "$key (flag): " . $attachment_id->get_error_message();

        continue;
    }

    update_post_meta($post->ID, 'flag_image_id', $attachment_id);
    $attached['flag']++;

    if ($old_flag_id && $old_flag_id !== $attachment_id && get_post($old_flag_id)) {
        wp_delete_attachment($old_flag_id, true);
    }
}

WP_CLI::success(sprintf(
    'Maps: %d attached, %d already present. Flags: %d attached/upgraded.',
    $attached['map'],
    $skipped['map'],
    $attached['flag']
));

if (!empty($failed)) {
    WP_CLI::warning(sprintf('%d image(s) failed:', count($failed)));
    foreach (array_slice($failed, 0, 20) as $line) {
        WP_CLI::log('  - ' . $line);
    }
}

/**
 * Download a remote image and attach it to a post as a media library
 * item, returning the new attachment ID (or a WP_Error on failure).
 */
function sideload_image_to_post(string $url, int $post_id, string $description)
{
    $tmp_file = download_url($url);

    if (is_wp_error($tmp_file)) {
        return $tmp_file;
    }

    $file_array = [
        'name' => basename(wp_parse_url($url, PHP_URL_PATH)),
        'tmp_name' => $tmp_file,
    ];

    $attachment_id = media_handle_sideload($file_array, $post_id, $description);

    if (is_wp_error($attachment_id)) {
        @unlink($tmp_file);

        return $attachment_id;
    }

    return $attachment_id;
}
