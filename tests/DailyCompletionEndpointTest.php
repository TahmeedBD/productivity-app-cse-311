<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/activities/service.php';
require_once __DIR__ . '/../src/daily_logs/service.php';
require_once __DIR__ . '/../src/checklist_items/service.php';
require_once __DIR__ . '/../src/daily_completions/service.php';
require_once __DIR__ . '/../src/daily_completions/list.php';

use PHPUnit\Framework\TestCase;

final class DailyCompletionEndpointTest extends TestCase
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
                name TEXT NOT NULL,
                UNIQUE(user_id, name)
            )',
        );

        $this->pdo->exec(
            'CREATE TABLE checklist_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id TEXT NOT NULL,
                activity_id INTEGER NOT NULL,
                min_duration_minutes INTEGER NOT NULL DEFAULT 20,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                UNIQUE(user_id, activity_id)
            )',
        );

        $this->pdo->exec(
            'CREATE TABLE daily_completions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id TEXT NOT NULL,
                daily_log_id INTEGER NOT NULL,
                checklist_item_id INTEGER NOT NULL,
                is_completed INTEGER NOT NULL DEFAULT 1,
                updated_at TEXT NULL,
                UNIQUE(daily_log_id, checklist_item_id)
            )',
        );
    }

    public function testListDailyCompletionSummariesResponseReturnsGroupedHistory(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Coding');
        $item = create_checklist_item(
            $this->pdo,
            'user-1',
            (int) $activity['id'],
        );
        $firstLog = get_or_create_daily_log($this->pdo, 'user-1', '2026-05-23');
        $secondLog = get_or_create_daily_log(
            $this->pdo,
            'user-1',
            '2026-05-24',
        );

        mark_checklist_item_complete_for_day(
            $this->pdo,
            'user-1',
            (int) $firstLog['id'],
            (int) $item['id'],
        );
        mark_checklist_item_complete_for_day(
            $this->pdo,
            'user-1',
            (int) $secondLog['id'],
            (int) $item['id'],
        );

        $response = build_list_daily_completion_summaries_response($this->pdo, [
            'id' => 'user-1',
        ]);

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['body']['ok']);
        $this->assertCount(1, $response['body']['summaries']);
        $this->assertSame(
            2,
            $response['body']['summaries'][0]['completed_days_count'],
        );
    }
}
