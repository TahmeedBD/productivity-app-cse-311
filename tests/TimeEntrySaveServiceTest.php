<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/daily_logs/helpers.php';
require_once __DIR__ . '/../src/daily_logs/service.php';
require_once __DIR__ . '/../src/time_entries/service.php';
require_once __DIR__ . '/../src/time_entries/save.php';

use PHPUnit\Framework\TestCase;

final class TimeEntrySaveServiceTest extends TestCase
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

    public function testSaveRunningEntryUpdatesValuesWithoutEndingEntry(): void
    {
        $runningEntry = start_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-20',
            '09:00:00',
        );
        $activity = create_activity($this->pdo, 'user-1', 'Work');
        $subtype = create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'Planning',
        );

        $savedEntry = save_running_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-20',
            [
                'activity_id' => (int) $activity['id'],
                'activity_subtype_id' => (int) $subtype['id'],
                'notes' => 'Daily planning',
            ],
        );

        $this->assertSame((int) $runningEntry['id'], (int) $savedEntry['id']);
        $this->assertNull($savedEntry['end']);
        $this->assertSame('running', $savedEntry['state']);
        $this->assertSame(
            (int) $activity['id'],
            (int) $savedEntry['activity_id'],
        );
        $this->assertSame(
            (int) $subtype['id'],
            (int) $savedEntry['activity_subtype_id'],
        );
        $this->assertSame('Daily planning', $savedEntry['notes']);
    }

    public function testSaveRunningEntryRejectsBlankActivity(): void
    {
        start_time_entry($this->pdo, 'user-1', '2026-05-20', '09:00:00');

        $this->expectException(\InvalidArgumentException::class);

        save_running_time_entry($this->pdo, 'user-1', '2026-05-20', [
            'notes' => 'Missing activity',
        ]);
    }
}
