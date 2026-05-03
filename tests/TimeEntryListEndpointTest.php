<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/daily_logs/helpers.php';
require_once __DIR__ . '/../src/daily_logs/service.php';
require_once __DIR__ . '/../src/time_entries/service.php';
require_once __DIR__ . '/../src/time_entries/today.php';

use PHPUnit\Framework\TestCase;

final class TimeEntryListEndpointTest extends TestCase
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

    public function testTodayTimeEntriesResponseReturnsEntriesInAscendingOrder(): void
    {
        start_time_entry($this->pdo, 'user-1', '2026-05-12', '09:00:00', 'One');
        start_time_entry($this->pdo, 'user-1', '2026-05-12', '10:15:00', 'Two');

        $response = build_today_time_entries_response(
            $this->pdo,
            ['id' => 'user-1'],
            '2026-05-12',
        );

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['body']['ok']);
        $this->assertCount(2, $response['body']['entries']);
        $this->assertSame(
            '2026-05-12 09:00:00',
            $response['body']['entries'][0]['start'],
        );
        $this->assertSame(
            '2026-05-12 10:15:00',
            $response['body']['entries'][1]['start'],
        );
    }

    public function testTodayTimeEntriesResponseReturnsEmptyEntriesWhenNoneExist(): void
    {
        $response = build_today_time_entries_response(
            $this->pdo,
            ['id' => 'user-1'],
            '2026-05-12',
        );

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['body']['ok']);
        $this->assertSame([], $response['body']['entries']);
    }
}
