<?php
/**
 * Unit tests for Schedule_Override. Like RotationServiceTest, this
 * deliberately does not bootstrap WordPress: parse() is pure, and
 * key_for_week()/week_for_key() are exercised via
 * set_data_for_testing() so they never reach get_theme_file_path().
 *
 * Run with: vendor/bin/phpunit tests/ScheduleOverrideTest.php
 */

declare(strict_types=1);

use CountryWeek\Services\Schedule_Override;
use PHPUnit\Framework\TestCase;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once __DIR__ . '/../theme/country-week/includes/utilities/class-date-utility.php';
require_once __DIR__ . '/../theme/country-week/includes/services/class-schedule-override.php';

final class ScheduleOverrideTest extends TestCase
{
    protected function tearDown(): void
    {
        Schedule_Override::reset_for_testing();
    }

    private function ny(string $when): DateTimeImmutable
    {
        return new DateTimeImmutable($when, new DateTimeZone('America/New_York'));
    }

    public function test_parse_reads_date_to_key_pairs(): void
    {
        $map = Schedule_Override::parse([
            'overrides' => [
                ['date' => '2026-08-30', 'country_key' => 'lebanon'],
                ['date' => '2026-09-06', 'country_key' => 'lesotho'],
            ],
        ]);

        $this->assertSame([
            '2026-08-30' => 'lebanon',
            '2026-09-06' => 'lesotho',
        ], $map);
    }

    public function test_parse_ignores_malformed_input(): void
    {
        $this->assertSame([], Schedule_Override::parse(null));
        $this->assertSame([], Schedule_Override::parse('not an array'));
        $this->assertSame([], Schedule_Override::parse([]));
        $this->assertSame([], Schedule_Override::parse(['overrides' => 'nope']));
    }

    public function test_parse_skips_incomplete_entries(): void
    {
        $map = Schedule_Override::parse([
            'overrides' => [
                ['date' => '2026-08-30'],
                ['country_key' => 'lebanon'],
                ['date' => '2026-09-06', 'country_key' => 'lesotho'],
            ],
        ]);

        $this->assertSame(['2026-09-06' => 'lesotho'], $map);
    }

    public function test_key_for_week_matches_the_exact_sunday(): void
    {
        Schedule_Override::set_data_for_testing(['2026-08-30' => 'lebanon']);

        $this->assertSame('lebanon', Schedule_Override::key_for_week($this->ny('2026-08-30 00:00:00')));
    }

    public function test_key_for_week_returns_null_for_an_unlisted_week(): void
    {
        Schedule_Override::set_data_for_testing(['2026-08-30' => 'lebanon']);

        $this->assertNull(Schedule_Override::key_for_week($this->ny('2026-08-23 00:00:00')));
    }

    public function test_week_for_key_is_the_reverse_lookup(): void
    {
        Schedule_Override::set_data_for_testing([
            '2026-08-30' => 'lebanon',
            '2026-12-06' => 'martinique',
        ]);

        $this->assertSame('2026-12-06', Schedule_Override::week_for_key('martinique')->format('Y-m-d'));
        $this->assertNull(Schedule_Override::week_for_key('laos'));
    }
}
