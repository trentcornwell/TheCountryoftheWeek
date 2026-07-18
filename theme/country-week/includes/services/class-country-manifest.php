<?php
/**
 * Loads the immutable, versioned country manifest bundled with the
 * theme and exposes the `manifest_key` post meta that ties a WordPress
 * post to one manifest entry.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Services;

use CountryWeek\CPT\Country_Post_Type;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * See docs/decisions/0001-deterministic-weekly-schedule.md: rotation
 * order must never be derived by re-sorting live post titles, because
 * an editorial rename would silently rewrite the schedule. This class
 * is the fix — includes/data/country-manifest.json is a frozen,
 * versioned file (regenerated only deliberately by
 * scripts/import-countries.php from data/country-index.json at the
 * repo root) where each entry has a stable `key` that never changes
 * even if a country's post title is later corrected or retranslated.
 * Country_Repository resolves posts to manifest positions via the
 * `manifest_key` meta value set at import time, not via title.
 */
class Country_Manifest
{
    private const META_KEY = 'manifest_key';

    /** @var array{manifest_version:int,anchor:array,countries:array[]}|null */
    private static ?array $data = null;

    public function register(): void
    {
        add_action('init', [$this, 'register_meta_field']);
    }

    public function register_meta_field(): void
    {
        register_post_meta(Country_Post_Type::POST_TYPE, self::META_KEY, [
            'type' => 'string',
            'single' => true,
            // Internal identity, not editorial content: intentionally
            // excluded from REST/editor exposure.
            'show_in_rest' => false,
            'sanitize_callback' => 'sanitize_key',
            'auth_callback' => fn () => current_user_can('edit_posts'),
        ]);
    }

    public static function meta_key(): string
    {
        return self::META_KEY;
    }

    /**
     * @return array{manifest_version:int,anchor:array{country_key:string,local_date:string,time:string,timezone:string},countries:array[]}
     */
    public static function load(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }

        $path = get_theme_file_path('includes/data/country-manifest.json');
        $json = file_exists($path) ? (string) file_get_contents($path) : '';
        $decoded = json_decode($json, true);

        if (!is_array($decoded) || empty($decoded['countries'])) {
            self::$data = [
                'manifest_version' => 0,
                'anchor' => ['country_key' => '', 'local_date' => '', 'time' => '00:00:00', 'timezone' => 'America/New_York'],
                'countries' => [],
            ];

            return self::$data;
        }

        self::$data = $decoded;

        return self::$data;
    }

    public static function version(): int
    {
        return (int) (self::load()['manifest_version'] ?? 0);
    }

    /**
     * @return array[] Ordered list of ['key'=>string,'name'=>string,'continent'=>string,'region'=>string].
     */
    public static function entries(): array
    {
        return self::load()['countries'] ?? [];
    }

    public static function anchor_key(): string
    {
        return self::load()['anchor']['country_key'] ?? '';
    }

    public static function find_entry(string $key): ?array
    {
        foreach (self::entries() as $entry) {
            if ($entry['key'] === $key) {
                return $entry;
            }
        }

        return null;
    }
}
