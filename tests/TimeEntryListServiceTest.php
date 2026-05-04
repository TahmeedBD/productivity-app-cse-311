<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/daily_logs/helpers.php';
require_once __DIR__ . '/../src/daily_logs/service.php';
require_once __DIR__ . '/../src/time_entries/service.php';

use PHPUnit\Framework\TestCase;

final class TimeEntryListServiceTest extends TestCase
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

    public function testListTodayTimeEntriesReturnsEmptyArrayWhenNoEntriesExist(): void
    {
        $entries = list_today_time_entries($this->pdo, 'user-1', '2026-05-11');

        $this->assertSame([], $entries);
    }

    public function testListTodayTimeEntriesReturnsEntriesSortedByStartDescending(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Deep Work');
        $subtype = create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'Coding',
        );

        add_past_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-11',
            '09:00:00',
            '10:00:00',
            'One',
            (int) $activity['id'],
            (int) $subtype['id'],
        );
        add_past_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-11',
            '10:15:00',
            '11:00:00',
            'Two',
            (int) $activity['id'],
        );
        start_time_entry($this->pdo, 'user-1', '2026-05-11', '11:30:00');

        $entries = list_today_time_entries($this->pdo, 'user-1', '2026-05-11');

        $this->assertCount(3, $entries);
        $this->assertSame('2026-05-11 11:30:00', $entries[0]['start']);
        $this->assertSame('2026-05-11 10:15:00', $entries[1]['start']);
        $this->assertSame('2026-05-11 09:00:00', $entries[2]['start']);
        $this->assertNull($entries[0]['end']);
        $this->assertSame('running', $entries[0]['state']);
        $this->assertSame('Deep Work', $entries[1]['activity_name']);
        $this->assertNull($entries[1]['activity_subtype_name']);
        $this->assertSame('Deep Work', $entries[2]['activity_name']);
        $this->assertSame('Coding', $entries[2]['activity_subtype_name']);
    }

    public function testListTodayTimeEntriesDoesNotLeakOtherUsersEntries(): void
    {
        $myActivity = create_activity($this->pdo, 'user-1', 'Mine');
        $otherActivity = create_activity($this->pdo, 'user-2', 'Theirs');

        add_past_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-11',
            '09:00:00',
            '09:30:00',
            'Mine',
            (int) $myActivity['id'],
        );
        add_past_time_entry(
            $this->pdo,
            'user-2',
            '2026-05-11',
            '09:30:00',
            '10:00:00',
            'Not mine',
            (int) $otherActivity['id'],
        );

        $entries = list_today_time_entries($this->pdo, 'user-1', '2026-05-11');

        $this->assertCount(1, $entries);
        $this->assertSame('Mine', $entries[0]['notes']);
    }

    public function testListTodayTimeEntriesIncludesActivityLabelsWhenSet(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Deep Work');
        $subtype = create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'Coding',
        );

        add_past_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-20',
            '10:00:00',
            '10:30:00',
            'Focus block',
            (int) $activity['id'],
            (int) $subtype['id'],
        );

        $entries = list_today_time_entries($this->pdo, 'user-1', '2026-05-20');

        $this->assertCount(1, $entries);
        $this->assertSame('Deep Work', $entries[0]['activity_name']);
        $this->assertSame('Coding', $entries[0]['activity_subtype_name']);
    }
}
