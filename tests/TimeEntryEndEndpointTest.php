<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/daily_logs/helpers.php';
require_once __DIR__ . '/../src/daily_logs/service.php';
require_once __DIR__ . '/../src/time_entries/service.php';
require_once __DIR__ . '/../src/time_entries/end.php';

use PHPUnit\Framework\TestCase;

final class TimeEntryEndEndpointTest extends TestCase
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

    public function testEndEntryResponseReturnsCompletedEntry(): void
    {
        start_time_entry($this->pdo, 'user-1', '2026-05-16', '09:00:00');
        $activity = create_activity($this->pdo, 'user-1', 'Work');

        $response = build_end_time_entry_response(
            $this->pdo,
            ['id' => 'user-1'],
            [
                'end' => '10:30:00',
                'activity_id' => $activity['id'],
                'notes' => 'Work',
            ],
            '2026-05-16',
        );

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['body']['ok']);
        $this->assertSame('completed', $response['body']['entry']['state']);
        $this->assertSame(
            '2026-05-16 10:30:00',
            $response['body']['entry']['end'],
        );
        $this->assertSame(
            (int) $activity['id'],
            (int) $response['body']['entry']['activity_id'],
        );
        $this->assertSame('Work', $response['body']['entry']['notes']);
    }

    public function testEndEntryResponseUsesCurrentTimeWhenEndIsMissing(): void
    {
        start_time_entry($this->pdo, 'user-1', '2026-05-16', '09:00:00');
        $activity = create_activity($this->pdo, 'user-1', 'Work');

        $response = build_end_time_entry_response(
            $this->pdo,
            ['id' => 'user-1'],
            ['activity_id' => $activity['id']],
            '2026-05-16',
            '10:45:00',
        );

        $this->assertSame(200, $response['status']);
        $this->assertSame(
            '2026-05-16 10:45:00',
            $response['body']['entry']['end'],
        );
    }

    public function testEndEntryResponseReturns422WhenNoRunningEntry(): void
    {
        $response = build_end_time_entry_response(
            $this->pdo,
            ['id' => 'user-1'],
            ['end' => '10:00:00'],
            '2026-05-16',
        );

        $this->assertSame(422, $response['status']);
        $this->assertFalse($response['body']['ok']);
        $this->assertArrayHasKey('error', $response['body']);
    }

    public function testEndEntryResponseReturns422WhenEndTimeIsInvalid(): void
    {
        start_time_entry($this->pdo, 'user-1', '2026-05-16', '09:00:00');
        $activity = create_activity($this->pdo, 'user-1', 'Work');

        $response = build_end_time_entry_response(
            $this->pdo,
            ['id' => 'user-1'],
            ['end' => '08:30:00', 'activity_id' => $activity['id']],
            '2026-05-16',
        );

        $this->assertSame(422, $response['status']);
        $this->assertFalse($response['body']['ok']);
    }

    public function testEndEntryResponseReturns422WhenActivityIsMissing(): void
    {
        start_time_entry($this->pdo, 'user-1', '2026-05-16', '09:00:00');

        $response = build_end_time_entry_response(
            $this->pdo,
            ['id' => 'user-1'],
            ['end' => '10:30:00'],
            '2026-05-16',
        );

        $this->assertSame(422, $response['status']);
        $this->assertFalse($response['body']['ok']);
    }
}
