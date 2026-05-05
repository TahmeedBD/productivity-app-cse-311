<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/daily_logs/helpers.php';
require_once __DIR__ . '/../src/daily_logs/service.php';
require_once __DIR__ . '/../src/time_entries/service.php';
require_once __DIR__ . '/../src/time_entries/add.php';
require_once __DIR__ . '/../src/time_entries/delete.php';

use PHPUnit\Framework\TestCase;

final class TimeEntryDeleteEndpointTest extends TestCase
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

    public function testDeleteTimeEntryResponseReturns200OnSuccess(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Work');
        $entry = add_past_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-22',
            '09:00:00',
            '10:00:00',
            'Delete me',
            (int) $activity['id'],
        );

        $response = build_delete_time_entry_response(
            $this->pdo,
            ['id' => 'user-1'],
            ['id' => $entry['id']],
        );

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['body']['ok']);
        $this->assertNull(
            find_time_entry_by_id($this->pdo, (int) $entry['id']),
        );
    }

    public function testDeleteTimeEntryResponseReturns422WhenIdMissing(): void
    {
        $response = build_delete_time_entry_response(
            $this->pdo,
            ['id' => 'user-1'],
            [],
        );

        $this->assertSame(422, $response['status']);
        $this->assertFalse($response['body']['ok']);
    }
}
