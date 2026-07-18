<?php
/**
 * Date/time helpers scoped to the site's single canonical time zone.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The Country of the Week always reasons about "Sunday 12:00 AM" in
 * America/New_York, regardless of the server's configured WordPress
 * timezone. Centralizing that here means the rotation math is never
 * accidentally computed against a different zone.
 */
class Date_Utility
{
    public const SITE_TIMEZONE = 'America/New_York';

    /**
     * Get the site's canonical timezone object.
     */
    public static function timezone(): \DateTimeZone
    {
        static $timezone = null;

        if ($timezone === null) {
            $timezone = new \DateTimeZone(self::SITE_TIMEZONE);
        }

        return $timezone;
    }

    /**
     * The current moment, expressed in the site's canonical timezone.
     */
    public static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', self::timezone());
    }

    /**
     * Parse a date/time string as if it were written in the site's
     * canonical timezone (used for the rotation start date constant).
     */
    public static function parse(string $datetime): \DateTimeImmutable
    {
        return new \DateTimeImmutable($datetime, self::timezone());
    }

    /**
     * Format a DateTimeImmutable for human display, e.g. "July 19, 2026".
     */
    public static function format_human(\DateTimeImmutable $date): string
    {
        return $date->format('F j, Y');
    }

    /**
     * A human-readable "Sunday–Saturday" label for the week a Sunday
     * date falls in, e.g. "July 19–25, 2026" or, when the week spans a
     * month/year boundary, "December 28, 2026 – January 3, 2027". Used
     * in place of an abstract "Week 1", "Week 2" counter wherever the
     * rotation's week is shown, since a real date range is more useful
     * to a reader than an arbitrary sequence number.
     */
    public static function week_range_label(\DateTimeImmutable $sunday): string
    {
        $saturday = $sunday->add(new \DateInterval('P6D'));

        if ($sunday->format('Y-m') === $saturday->format('Y-m')) {
            return $sunday->format('F j') . '–' . $saturday->format('j') . ', ' . $saturday->format('Y');
        }

        if ($sunday->format('Y') === $saturday->format('Y')) {
            return $sunday->format('F j') . ' – ' . $saturday->format('F j') . ', ' . $saturday->format('Y');
        }

        return $sunday->format('F j, Y') . ' – ' . $saturday->format('F j, Y');
    }
}
