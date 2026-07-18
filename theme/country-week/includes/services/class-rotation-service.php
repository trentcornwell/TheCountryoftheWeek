<?php
/**
 * Perpetual weekly rotation engine.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Services;

use CountryWeek\Utilities\Date_Utility;
use DateInterval;
use DateTimeImmutable;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Determines which alphabetical position is "active" for any given moment
 * in time. This class knows nothing about WordPress posts — it is a pure
 * function of (start date, current date, list length), which makes it
 * trivial to unit test (see tests/RotationServiceTest.php) and guarantees
 * the schedule can run forever without any stored "current country" value
 * that could drift or require manual correction.
 *
 * The schedule is entirely derived, never stored:
 *   weeks_elapsed = floor(days_between(start, now) / 7)
 *   active_index  = weeks_elapsed mod count
 *
 * Once weeks_elapsed exceeds the list length it simply wraps via modulo,
 * so the last country is followed by the first, forever.
 */
class Rotation_Service
{
    /**
     * The moment the rotation begins: the Sunday 12:00 AM America/New_York
     * on which the launch country first becomes featured. This is a
     * fixed historical fact about the project launch, not something
     * that should ever change, so it is a constant rather than a setting.
     */
    public const ROTATION_START = '2026-07-19 00:00:00';

    /**
     * The rotation does not start at the alphabetically-first country —
     * it starts at Kiribati (manifest key "kiribati") and proceeds
     * alphabetically from there, wrapping back to the alphabetical
     * beginning after the last country. Every method on this class
     * works in terms of an abstract "position" where position 0 is
     * always this launch country; Services\Country_Repository and
     * Services\Country_Manifest are responsible for translating between
     * that abstract position and an actual manifest-ordered index (see
     * Country_Repository::launch_offset()).
     */

    /**
     * Get the rotation start date as a DateTimeImmutable in the site
     * timezone.
     */
    public static function start_date(): DateTimeImmutable
    {
        return Date_Utility::parse(self::ROTATION_START);
    }

    /**
     * Whether the rotation has begun as of the given moment. Before the
     * start date there is no "active" country yet — the homepage should
     * show a countdown rather than an invented answer.
     */
    public static function has_started(?DateTimeImmutable $now = null): bool
    {
        $now = $now ?? Date_Utility::now();

        return $now >= self::start_date();
    }

    /**
     * Full weeks elapsed between the rotation start and the given moment.
     * Returns 0 for any moment before the start (callers should check
     * has_started() first if they need to distinguish "not started" from
     * "week zero").
     */
    public static function weeks_elapsed(?DateTimeImmutable $now = null): int
    {
        $now = $now ?? Date_Utility::now();
        $start = self::start_date();

        if ($now < $start) {
            return 0;
        }

        $days = self::whole_days_between($start, $now);

        return intdiv($days, 7);
    }

    /**
     * The alphabetical list index that is active for the given moment,
     * wrapped forever via modulo. $count must be the total number of
     * countries in the alphabetical rotation list.
     */
    public static function active_index(int $count, ?DateTimeImmutable $now = null): int
    {
        if ($count < 1) {
            return 0;
        }

        return self::weeks_elapsed($now) % $count;
    }

    /**
     * The date/time at which the country at $index in the alphabetical
     * list is (or will be) featured. Because the schedule repeats forever,
     * a given index recurs every $count weeks; this returns the
     * occurrence nearest to (and not before) $now.
     */
    public static function date_for_index(int $index, int $count, ?DateTimeImmutable $now = null): DateTimeImmutable
    {
        $now = $now ?? Date_Utility::now();
        $start = self::start_date();

        if ($count < 1) {
            return $start;
        }

        $current_cycle_week = self::weeks_elapsed($now);
        $current_index = $current_cycle_week % $count;

        // Offset (in weeks) from the current active week to reach $index,
        // always moving forward (0 if $index is the currently active one).
        $offset = ($index - $current_index + $count) % $count;
        $target_week = $current_cycle_week + $offset;

        return $start->add(new DateInterval('P' . ($target_week * 7) . 'D'));
    }

    /**
     * The most recent date/time (at or before $now) the country at
     * $index was featured. Used for "date featured" on countries that
     * have already had their turn at least once.
     */
    public static function most_recent_date_for_index(int $index, int $count, ?DateTimeImmutable $now = null): DateTimeImmutable
    {
        $now = $now ?? Date_Utility::now();
        $next = self::date_for_index($index, $count, $now);

        if ($next > $now && $count > 0) {
            return $next->sub(new DateInterval('P' . ($count * 7) . 'D'));
        }

        return $next;
    }

    /**
     * The 1-based week number of the entire rotation (not just the
     * current cycle) — i.e. how many Sundays have elapsed since launch.
     */
    public static function week_number(?DateTimeImmutable $now = null): int
    {
        return self::weeks_elapsed($now) + 1;
    }

    /**
     * Whole days between two moments, ignoring any partial-day
     * remainder caused by DST transitions within the range.
     */
    private static function whole_days_between(DateTimeImmutable $start, DateTimeImmutable $now): int
    {
        $diff = $start->diff($now);

        return (int) $diff->format('%a');
    }
}
