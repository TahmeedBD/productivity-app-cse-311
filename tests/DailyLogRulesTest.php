<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/daily_logs/helpers.php';

use PHPUnit\Framework\TestCase;

final class DailyLogRulesTest extends TestCase
{
    public function testFallbackDailyWindowUsesDefaultWakeAndSleepTimes(): void
    {
        $this->assertSame(
            [
                'wake_time' => '08:00:00',
                'sleep_time' => '23:00:00',
            ],
            default_daily_log_times(),
        );
    }

    public function testEntryInsideAwakeWindowIsAccepted(): void
    {
        $this->assertTrue(
            is_entry_within_awake_window(
                '09:00:00',
                '10:30:00',
                '08:00:00',
                '23:00:00',
            ),
        );
    }

    public function testEntryBeforeWakeTimeIsRejected(): void
    {
        $this->assertFalse(
            is_entry_within_awake_window(
                '07:45:00',
                '08:30:00',
                '08:00:00',
                '23:00:00',
            ),
        );
    }

    public function testEntryAfterSleepTimeIsRejected(): void
    {
        $this->assertFalse(
            is_entry_within_awake_window(
                '22:30:00',
                '23:15:00',
                '08:00:00',
                '23:00:00',
            ),
        );
    }

    public function testOverlappingEntryIsDetected(): void
    {
        $existingEntries = [
            ['start' => '09:00:00', 'end' => '10:00:00'],
            ['start' => '11:00:00', 'end' => '12:00:00'],
        ];

        $this->assertTrue(
            has_overlapping_time_entry(
                ['start' => '09:30:00', 'end' => '10:15:00'],
                $existingEntries,
            ),
        );
    }

    public function testSeparatedEntryIsAccepted(): void
    {
        $existingEntries = [
            ['start' => '09:00:00', 'end' => '10:00:00'],
            ['start' => '11:00:00', 'end' => '12:00:00'],
        ];

        $this->assertFalse(
            has_overlapping_time_entry(
                ['start' => '10:15:00', 'end' => '10:45:00'],
                $existingEntries,
            ),
        );
    }
}
