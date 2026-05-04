<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/daily_logs/helpers.php';
require_once __DIR__ . '/../src/daily_logs/service.php';
require_once __DIR__ . '/../src/time_entries/service.php';

use PHPUnit\Framework\TestCase;

final class TimeEntryEndServiceTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(
            \PDO::ATTR_DEFAULT_FETCH_MODE,
            \PDO::FETCH_ASSOC,
        );

        $this->pdo->exec(
            'CREATE TABLE daily_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id TEXT NOT NULL,
                date TEXT NOT NULL,
                wake_time TEXT,
                sleep_time TEXT,
                UNIQUE(user_id, date)
            )',
        );

        $this->pdo->exec(
            'CREATE TABLE activities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id TEXT NOT NULL,
                name TEXT NOT NULL
            )',
        );

        $this->pdo->exec(
            'CREATE TABLE activity_subtypes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                activity_id INTEGER NOT NULL,
                name TEXT NOT NULL
            )',
        );

        $this->pdo->exec(
            'CREATE TABLE time_entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                daily_log_id INTEGER NOT NULL,
                activity_id INTEGER NULL,
                activity_subtype_id INTEGER NULL,
                start TEXT NOT NULL,
                end TEXT NULL,
                state TEXT NOT NULL,
                notes TEXT NULL
            )',
        );
    }

    public function testEndsRunningEntryAtSpecifiedTime(): void
    {
        start_time_entry($this->pdo, 'user-1', '2026-05-15', '09:00:00');
        $activity = create_activity($this->pdo, 'user-1', 'Work');
        $subtype = create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'Coding',
        );

        $entry = end_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-15',
            '10:30:00',
            [
                'activity_id' => (int) $activity['id'],
                'activity_subtype_id' => (int) $subtype['id'],
                'notes' => 'Work',
            ],
        );

        $this->assertSame('2026-05-15 10:30:00', $entry['end']);
        $this->assertSame('completed', $entry['state']);
        $this->assertSame((int) $activity['id'], (int) $entry['activity_id']);
        $this->assertSame(
            (int) $subtype['id'],
            (int) $entry['activity_subtype_id'],
        );
        $this->assertSame('Work', $entry['notes']);
    }

    public function testEndedEntryStartRemainsUnchanged(): void
    {
        start_time_entry($this->pdo, 'user-1', '2026-05-15', '09:00:00');

        $entry = end_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-15',
            '10:30:00',
            [
                'activity_id' => create_activity($this->pdo, 'user-1', 'Work')[
                    'id'
                ],
            ],
        );

        $this->assertSame('2026-05-15 09:00:00', $entry['start']);
    }

    public function testThrowsWhenNoRunningEntryExists(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        end_time_entry($this->pdo, 'user-1', '2026-05-15', '10:00:00');
    }

    public function testThrowsWhenNoRunningEntryExistsAfterAllEntriesCompleted(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Work');
        start_time_entry($this->pdo, 'user-1', '2026-05-15', '09:00:00');
        end_time_entry($this->pdo, 'user-1', '2026-05-15', '10:00:00', [
            'activity_id' => (int) $activity['id'],
        ]);

        $this->expectException(\InvalidArgumentException::class);

        end_time_entry($this->pdo, 'user-1', '2026-05-15', '12:00:00', [
            'activity_id' => (int) $activity['id'],
        ]);
    }

    public function testThrowsWhenEndTimeIsAtEntryStart(): void
    {
        start_time_entry($this->pdo, 'user-1', '2026-05-15', '09:00:00');
        $activity = create_activity($this->pdo, 'user-1', 'Work');

        $this->expectException(\InvalidArgumentException::class);

        end_time_entry($this->pdo, 'user-1', '2026-05-15', '09:00:00', [
            'activity_id' => (int) $activity['id'],
        ]);
    }

    public function testThrowsWhenEndTimeIsBeforeEntryStart(): void
    {
        start_time_entry($this->pdo, 'user-1', '2026-05-15', '09:00:00');
        $activity = create_activity($this->pdo, 'user-1', 'Work');

        $this->expectException(\InvalidArgumentException::class);

        end_time_entry($this->pdo, 'user-1', '2026-05-15', '08:30:00', [
            'activity_id' => (int) $activity['id'],
        ]);
    }

    public function testThrowsWhenEndTimeIsAfterSleepTime(): void
    {
        start_time_entry($this->pdo, 'user-1', '2026-05-15', '09:00:00');
        $activity = create_activity($this->pdo, 'user-1', 'Work');

        $this->expectException(\InvalidArgumentException::class);

        end_time_entry($this->pdo, 'user-1', '2026-05-15', '23:30:00', [
            'activity_id' => (int) $activity['id'],
        ]);
    }

    public function testAllowsEndTimeAtExactlySleepTime(): void
    {
        start_time_entry($this->pdo, 'user-1', '2026-05-15', '09:00:00');
        $activity = create_activity($this->pdo, 'user-1', 'Work');

        $entry = end_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-15',
            '23:00:00',
            ['activity_id' => (int) $activity['id']],
        );

        $this->assertSame('completed', $entry['state']);
        $this->assertSame('2026-05-15 23:00:00', $entry['end']);
    }

    public function testStopRejectsBlankActivity(): void
    {
        start_time_entry($this->pdo, 'user-1', '2026-05-15', '09:00:00');

        $this->expectException(\InvalidArgumentException::class);

        end_time_entry($this->pdo, 'user-1', '2026-05-15', '10:30:00', [
            'notes' => 'Missing activity',
        ]);
    }
}
