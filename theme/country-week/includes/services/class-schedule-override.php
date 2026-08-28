<?php
/**
 * Bounded, near-term exceptions to the perpetual alphabetical rotation.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Services;

use CountryWeek\Utilities\Date_Utility;
use DateTimeImmutable;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Loads includes/data/schedule-overrides.json — a small, explicit,
 * git-tracked map of local schedule week (Sunday, America/New_York) to
 * a manifest key that should air that week instead of whatever
 * Rotation_Service's pure alphabetical math would otherwise select.
 *
 * This is deliberately a thin, bounded escape hatch, not a second
 * scheduling engine: Rotation_Service and Country_Manifest stay exactly
 * as pure and untouched as before (see docs/decisions/0001). The
 * country that would have naturally aired an overridden week is simply
 * skipped for this one cycle — it airs on its own next natural
 * occurrence, one full rotation length later — so nothing here ever
 * permanently changes the manifest, the count, or any other country's
 * position. See docs/decisions/0006-temporary-schedule-overrides.md.
 */
class Schedule_Override
{
    /** @var array<string,string>|null date (Y-m-d) => manifest key */
    private static ?array $data = null;

    /**
     * @return array<string,string> date (Y-m-d) => manifest key
     */
    public static function load(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }

        $path = get_theme_file_path('includes/data/schedule-overrides.json');
        $json = file_exists($path) ? (string) file_get_contents($path) : '';

        self::$data = self::parse(json_decode($json, true));

        return self::$data;
    }

    /**
     * Pure parsing step, kept separate from file I/O so it can be unit
     * tested without a WordPress bootstrap.
     *
     * @param mixed $decoded
     * @return array<string,string> date (Y-m-d) => manifest key
     */
    public static function parse($decoded): array
    {
        $map = [];

        if (!is_array($decoded) || empty($decoded['overrides']) || !is_array($decoded['overrides'])) {
            return $map;
        }

        foreach ($decoded['overrides'] as $entry) {
            if (is_array($entry) && !empty($entry['date']) && !empty($entry['country_key'])) {
                $map[(string) $entry['date']] = (string) $entry['country_key'];
            }
        }

        return $map;
    }

    /**
     * The manifest key that should air for the local schedule week
     * starting on $week_start, or null if that week has no override.
     */
    public static function key_for_week(DateTimeImmutable $week_start): ?string
    {
        return self::load()[$week_start->format('Y-m-d')] ?? null;
    }

    /**
     * The overridden week (if any) for a given manifest key — the
     * reverse lookup used to detect that a country's *natural* slot has
     * been given to someone else, and to report a country's own
     * override date directly.
     */
    public static function week_for_key(string $key): ?DateTimeImmutable
    {
        foreach (self::load() as $date => $country_key) {
            if ($country_key === $key) {
                return Date_Utility::parse($date . ' 00:00:00');
            }
        }

        return null;
    }

    /**
     * Test-only: inject a parsed override map without touching the
     * filesystem or requiring a WordPress bootstrap.
     *
     * @param array<string,string> $data
     */
    public static function set_data_for_testing(array $data): void
    {
        self::$data = $data;
    }

    public static function reset_for_testing(): void
    {
        self::$data = null;
    }
}
