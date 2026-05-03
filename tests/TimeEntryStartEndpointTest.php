<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/daily_logs/helpers.php';
require_once __DIR__ . '/../src/daily_logs/service.php';
require_once __DIR__ . '/../src/time_entries/service.php';
require_once __DIR__ . '/../src/time_entries/start.php';

use PHPUnit\Framework\TestCase;

final class TimeEntryStartEndpointTest extends TestCase
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

    public function testStartEntryResponseReturnsCreatedRunningEntry(): void
    {
        $response = build_start_time_entry_response(
            $this->pdo,
            ['id' => 'user-1'],
            [
                'start' => '09:00:00',
                'notes' => 'Deep work',
            ],
            '2026-05-10',
        );

        $this->assertSame(201, $response['status']);
        $this->assertTrue($response['body']['ok']);
        $this->assertSame('running', $response['body']['entry']['state']);
        $this->assertSame('Deep work', $response['body']['entry']['notes']);
        $this->assertNull($response['body']['entry']['end']);
    }

    public function testStartEntryResponseUsesCurrentTimeWhenStartIsMissing(): void
    {
        $response = build_start_time_entry_response(
            $this->pdo,
            ['id' => 'user-1'],
            ['notes' => 'Missing start'],
            '2026-05-10',
            '11:20:00',
        );

        $this->assertSame(201, $response['status']);
        $this->assertTrue($response['body']['ok']);
        $this->assertSame(
            '2026-05-10 11:20:00',
            $response['body']['entry']['start'],
        );
        $this->assertSame('Missing start', $response['body']['entry']['notes']);
    }
}
