<?php
/**
 * Unit tests for Rotation_Service. Deliberately does not bootstrap
 * WordPress — Rotation_Service is pure date math, so these tests just
 * require the two source files directly and run in milliseconds.
 *
 * Run with: vendor/bin/phpunit tests/RotationServiceTest.php
 * (or, with only a phar install: php phpunit.phar tests/RotationServiceTest.php)
 */

declare(strict_types=1);

use CountryWeek\Services\Rotation_Service;
use PHPUnit\Framework\TestCase;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once __DIR__ . '/../theme/country-week/includes/utilities/class-date-utility.php';
require_once __DIR__ . '/../theme/country-week/includes/services/class-rotation-service.php';

final class RotationServiceTest extends TestCase
{
    private function ny(string $when): DateTimeImmutable
    {
        return new DateTimeImmutable($when, new DateTimeZone('America/New_York'));
    }

    public function test_has_not_started_before_launch(): void
    {
        $this->assertFalse(Rotation_Service::has_started($this->ny('2026-07-18 23:59:59')));
    }

    public function test_has_started_at_exact_launch_instant(): void
    {
        $this->assertTrue(Rotation_Service::has_started($this->ny('2026-07-19 00:00:00')));
    }

    public function test_active_index_is_zero_at_launch(): void
    {
        // Index 0 must be Kiribati per the project spec.
        $this->assertSame(0, Rotation_Service::active_index(195, $this->ny('2026-07-19 00:00:00')));
    }

    public function test_active_index_stays_zero_until_next_sunday(): void
    {
        $this->assertSame(0, Rotation_Service::active_index(195, $this->ny('2026-07-25 23:59:59')));
    }

    public function test_active_index_advances_on_second_sunday(): void
    {
        $this->assertSame(1, Rotation_Service::active_index(195, $this->ny('2026-07-26 00:00:00')));
    }

    public function test_active_index_advances_weekly(): void
    {
        $this->assertSame(10, Rotation_Service::active_index(195, $this->ny('2026-09-27 00:00:00')));
    }

    public function test_active_index_wraps_after_final_country(): void
    {
        // With a 5-country list, week 5 (index would be 5) must wrap to 0.
        $start = Rotation_Service::start_date();
        $fiveWeeksLater = $start->add(new DateInterval('P35D'));

        $this->assertSame(0, Rotation_Service::active_index(5, $fiveWeeksLater));
        $this->assertSame(1, Rotation_Service::active_index(5, $fiveWeeksLater->add(new DateInterval('P7D'))));
    }

    public function test_active_index_wraps_forever_across_many_cycles(): void
    {
        $start = Rotation_Service::start_date();
        // 195 countries * 7 days * 3 full cycles + 2 weeks into a 4th cycle.
        $farFuture = $start->add(new DateInterval('P' . ((195 * 3 + 2) * 7) . 'D'));

        $this->assertSame(2, Rotation_Service::active_index(195, $farFuture));
    }

    public function test_week_number_is_one_based(): void
    {
        $this->assertSame(1, Rotation_Service::week_number($this->ny('2026-07-19 00:00:00')));
        $this->assertSame(2, Rotation_Service::week_number($this->ny('2026-07-26 00:00:00')));
    }

    public function test_date_for_index_returns_next_upcoming_occurrence(): void
    {
        // At launch (index 0 active, count=3), index 2's next occurrence
        // should be 2 weeks after launch.
        $now = $this->ny('2026-07-19 00:00:00');
        $expected = $this->ny('2026-08-02 00:00:00');

        $this->assertEquals(
            $expected->format('Y-m-d'),
            Rotation_Service::date_for_index(2, 3, $now)->format('Y-m-d')
        );
    }

    public function test_date_for_index_returns_today_for_currently_active_index(): void
    {
        $now = $this->ny('2026-07-19 00:00:00');

        $this->assertEquals(
            $now->format('Y-m-d'),
            Rotation_Service::date_for_index(0, 195, $now)->format('Y-m-d')
        );
    }

    public function test_most_recent_date_for_index_looks_backward_when_needed(): void
    {
        // count=3, currently at index 1 (one week after launch). Index 2's
        // most recent occurrence was in the previous cycle, before launch
        // math even started — but since count=3 and we're only 1 week in,
        // index 2 has never occurred yet going forward from a full cycle
        // back, so most_recent should equal the *upcoming* one only if
        // next <= now; otherwise it looks one full cycle back.
        $now = $this->ny('2026-07-26 00:00:00'); // index 1 active
        $mostRecent = Rotation_Service::most_recent_date_for_index(2, 3, $now);

        $this->assertTrue($mostRecent <= $now);
    }
}
