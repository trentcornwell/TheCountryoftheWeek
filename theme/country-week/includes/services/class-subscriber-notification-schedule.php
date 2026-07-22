<?php
/**
 * Pure "should this subscriber be emailed right now" predicate for the
 * weekly upcoming-country preview.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Services;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * No WP calls, styled after Rotation_Service — a pure function of
 * (now, subscriber time zone, boundary, already-notified) so it can be
 * unit tested without spinning up WordPress (see
 * tests/SubscriberNotificationScheduleTest.php).
 *
 * $boundary is always the exact America/New_York instant the *upcoming*
 * country becomes active — Country_Repository::next_scheduled_date()'s
 * answer, passed in rather than recomputed here, so this class can
 * never drift from the single source of truth for rotation dates (see
 * docs/decisions/0002-per-subscriber-timezone-weekly-email.md).
 *
 * Why the notification window is [Saturday local noon, $boundary):
 * evaluating "Saturday local noon" in *any* IANA time zone (even
 * UTC+14 or UTC-12 extremes) always converts to an instant within
 * Friday-evening-through-Saturday-evening America/New_York time —
 * always safely before the Sunday 00:00 NY boundary that flips which
 * country is "upcoming". So no per-time-zone special-casing is needed
 * for *which* country to feature. The window is capped at $boundary
 * (not open-ended) because that safety property only holds up to the
 * moment the NY clock itself crosses the boundary — past that, what
 * was "upcoming" is now current, and a late send evaluated past this
 * point would need to look one country further ahead.
 *
 * Capping the window there, rather than checking a single hour, is
 * also what makes the cron job self-healing: if WP-Cron's hourly tick
 * drifts or a run is missed, the next run that does fire will still
 * find the predicate true and send — a bit late, but correct — and the
 * already-notified flag then prevents any repeat.
 */
class Subscriber_Notification_Schedule
{
    public const LOCAL_SEND_HOUR = 12;

    /**
     * The most recent local Saturday 12:00:00, in $subscriber_timezone,
     * strictly before $boundary.
     */
    public static function window_start(DateTimeImmutable $boundary, DateTimeZone $subscriber_timezone): DateTimeImmutable
    {
        $local_boundary = $boundary->setTimezone($subscriber_timezone);

        $saturday = $local_boundary;

        while ((int) $saturday->format('N') !== 6) {
            $saturday = $saturday->sub(new DateInterval('P1D'));
        }

        $window_start = new DateTimeImmutable(
            $saturday->format('Y-m-d') . ' ' . sprintf('%02d:00:00', self::LOCAL_SEND_HOUR),
            $subscriber_timezone
        );

        // Defensive: the class docblock's safety proof means this
        // shouldn't happen, but if the computed same-week Saturday
        // noon ever lands at/after the boundary, step back a full week
        // rather than return a window that can never be "current".
        if ($window_start >= $boundary) {
            $window_start = $window_start->sub(new DateInterval('P7D'));
        }

        return $window_start;
    }

    public static function should_notify(
        DateTimeImmutable $now,
        DateTimeZone $subscriber_timezone,
        DateTimeImmutable $boundary,
        bool $already_notified_this_cycle
    ): bool {
        if ($already_notified_this_cycle) {
            return false;
        }

        $window_start = self::window_start($boundary, $subscriber_timezone);

        return $now >= $window_start && $now < $boundary;
    }
}
