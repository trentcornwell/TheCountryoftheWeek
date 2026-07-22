<?php
/**
 * Unit tests for Subscriber_Notification_Schedule. Deliberately does
 * not bootstrap WordPress — this class is pure date math, so these
 * tests just require the source file directly and run in milliseconds.
 *
 * Run with: vendor/bin/phpunit tests/SubscriberNotificationScheduleTest.php
 * (or, with only a phar install: php phpunit.phar tests/SubscriberNotificationScheduleTest.php)
 */

declare(strict_types=1);

use CountryWeek\Services\Subscriber_Notification_Schedule;
use PHPUnit\Framework\TestCase;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once __DIR__ . '/../theme/country-week/includes/services/class-subscriber-notification-schedule.php';

final class SubscriberNotificationScheduleTest extends TestCase
{
    /**
     * The boundary used throughout: the exact NY instant an upcoming
     * country becomes active — always a Sunday 00:00:00 America/New_York.
     */
    private function boundary(string $when = '2026-07-26 00:00:00'): DateTimeImmutable
    {
        return new DateTimeImmutable($when, new DateTimeZone('America/New_York'));
    }

    private function utc(string $when): DateTimeImmutable
    {
        return new DateTimeImmutable($when, new DateTimeZone('UTC'));
    }

    public function test_should_notify_at_exact_local_noon_saturday_ny(): void
    {
        $tz = new DateTimeZone('America/New_York');
        // Saturday 2026-07-25 12:00:00 NY = the window's opening instant.
        $now = $this->boundary('2026-07-25 12:00:00');

        $this->assertTrue(Subscriber_Notification_Schedule::should_notify($now, $tz, $this->boundary(), false));
    }

    public function test_should_not_notify_before_local_noon_saturday(): void
    {
        $tz = new DateTimeZone('America/New_York');
        $now = $this->boundary('2026-07-25 11:59:59');

        $this->assertFalse(Subscriber_Notification_Schedule::should_notify($now, $tz, $this->boundary(), false));
    }

    public function test_should_not_notify_at_or_after_the_boundary(): void
    {
        $tz = new DateTimeZone('America/New_York');

        $this->assertFalse(Subscriber_Notification_Schedule::should_notify($this->boundary(), $tz, $this->boundary(), false));
        $this->assertFalse(Subscriber_Notification_Schedule::should_notify($this->boundary('2026-07-26 00:00:01'), $tz, $this->boundary(), false));
    }

    public function test_already_notified_short_circuits_regardless_of_time(): void
    {
        $tz = new DateTimeZone('America/New_York');
        $now = $this->boundary('2026-07-25 12:00:00');

        $this->assertFalse(Subscriber_Notification_Schedule::should_notify($now, $tz, $this->boundary(), true));
    }

    /**
     * Kiritimati (Line Islands, Kiribati), UTC+14 — the most extreme
     * positive offset in real-world use. Saturday local noon there is
     * Friday 18:00:00 America/New_York, well inside the window.
     */
    public function test_extreme_positive_offset_utc_plus_14(): void
    {
        $tz = new DateTimeZone('Pacific/Kiritimati');
        $now = new DateTimeImmutable('2026-07-25 12:00:00', $tz);

        $this->assertTrue(Subscriber_Notification_Schedule::should_notify($now, $tz, $this->boundary(), false));

        // Sanity-check the claim above: this instant is Friday evening in NY.
        $ny = $now->setTimezone(new DateTimeZone('America/New_York'));
        $this->assertSame('2026-07-24', $ny->format('Y-m-d'));
    }

    /**
     * Baker Island, UTC-12 — the most extreme negative offset. Saturday
     * local noon there is Saturday 20:00:00 America/New_York, still
     * inside the current rotation week.
     */
    public function test_extreme_negative_offset_utc_minus_12(): void
    {
        $tz = new DateTimeZone('Etc/GMT+12'); // POSIX-inverted: this is UTC-12.
        $now = new DateTimeImmutable('2026-07-25 12:00:00', $tz);

        $this->assertTrue(Subscriber_Notification_Schedule::should_notify($now, $tz, $this->boundary(), false));

        $ny = $now->setTimezone(new DateTimeZone('America/New_York'));
        $this->assertSame('2026-07-25', $ny->format('Y-m-d'));
    }

    /**
     * A subscriber's local timezone can observe DST differently than
     * America/New_York, or not at all — window_start must still land
     * on the correct wall-clock Saturday noon in their zone.
     */
    public function test_timezone_with_no_dst(): void
    {
        $tz = new DateTimeZone('Asia/Tokyo'); // UTC+9, no DST.
        $now = new DateTimeImmutable('2026-07-25 12:00:00', $tz);

        $this->assertTrue(Subscriber_Notification_Schedule::should_notify($now, $tz, $this->boundary(), false));
    }

    public function test_self_healing_catch_up_after_missed_run(): void
    {
        $tz = new DateTimeZone('America/New_York');
        // A run that should have fired at Saturday noon didn't happen
        // until Saturday 22:00 — still well before the Sunday boundary,
        // so the predicate must still be true.
        $late = $this->boundary('2026-07-25 22:00:00');

        $this->assertTrue(Subscriber_Notification_Schedule::should_notify($late, $tz, $this->boundary(), false));
    }

    public function test_window_start_is_exactly_local_noon_the_saturday_before_boundary(): void
    {
        $tz = new DateTimeZone('America/Los_Angeles');
        $window_start = Subscriber_Notification_Schedule::window_start($this->boundary(), $tz);

        $this->assertSame('2026-07-25 12:00:00', $window_start->format('Y-m-d H:i:s'));
        $this->assertSame('6', $window_start->format('N')); // ISO weekday 6 = Saturday.
    }
}
