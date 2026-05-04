<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/daily_logs/helpers.php';
require_once __DIR__ . '/../src/daily_logs/service.php';
require_once __DIR__ . '/../src/time_entries/service.php';
require_once __DIR__ . '/../src/time_entries/add.php';

use PHPUnit\Framework\TestCase;

final class TimeEntryAddPastEndpointTest extends TestCase
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

    public function testAddPastEntryResponseReturnsCreatedCompletedEntry(): void
    {
        $response = build_add_past_time_entry_response(
            $this->pdo,
            ['id' => 'user-1'],
            [
                'start' => '09:00:00',
                'end' => '10:30:00',
                'notes' => 'Deep work',
            ],
            '2026-05-18',
        );

        $this->assertSame(201, $response['status']);
        $this->assertTrue($response['body']['ok']);
        $this->assertSame('completed', $response['body']['entry']['state']);
        $this->assertSame(
            '2026-05-18 09:00:00',
            $response['body']['entry']['start'],
        );
        $this->assertSame(
            '2026-05-18 10:30:00',
            $response['body']['entry']['end'],
        );
        $this->assertSame('Deep work', $response['body']['entry']['notes']);
    }

    public function testAddPastEntryResponseReturns422WhenStartIsMissing(): void
    {
        $response = build_add_past_time_entry_response(
            $this->pdo,
            ['id' => 'user-1'],
            [
                'end' => '10:30:00',
                'notes' => 'Missing start',
            ],
            '2026-05-18',
        );

        $this->assertSame(422, $response['status']);
        $this->assertFalse($response['body']['ok']);
        $this->assertArrayHasKey('error', $response['body']);
    }

    public function testAddPastEntryResponseReturns422WhenEndIsMissing(): void
    {
        $response = build_add_past_time_entry_response(
            $this->pdo,
            ['id' => 'user-1'],
            [
                'start' => '09:00:00',
                'notes' => 'Missing end',
            ],
            '2026-05-18',
        );

        $this->assertSame(422, $response['status']);
        $this->assertFalse($response['body']['ok']);
        $this->assertArrayHasKey('error', $response['body']);
    }

    public function testAddPastEntryResponseReturns422WhenEntryViolatesRules(): void
    {
        $response = build_add_past_time_entry_response(
            $this->pdo,
            ['id' => 'user-1'],
            [
                'start' => '07:00:00',
                'end' => '08:30:00',
                'notes' => 'Before wake time',
            ],
            '2026-05-18',
        );

        $this->assertSame(422, $response['status']);
        $this->assertFalse($response['body']['ok']);
    }

    public function testAddPastEntryResponseAcceptsEmptyNotes(): void
    {
        $response = build_add_past_time_entry_response(
            $this->pdo,
            ['id' => 'user-1'],
            [
                'start' => '09:00:00',
                'end' => '10:00:00',
            ],
            '2026-05-18',
        );

        $this->assertSame(201, $response['status']);
        $this->assertSame('', $response['body']['entry']['notes']);
    }
}
