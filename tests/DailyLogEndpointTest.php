<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/daily_logs/helpers.php';
require_once __DIR__ . '/../src/daily_logs/schedule.php';
require_once __DIR__ . '/../src/daily_logs/service.php';
require_once __DIR__ . '/../src/daily_logs/today.php';
use PHPUnit\Framework\TestCase;

final class DailyLogEndpointTest extends TestCase
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
    }

    public function testTodayDailyLogResponseCreatesMissingLog(): void
    {
        $response = build_today_daily_log_response(
            $this->pdo,
            ['id' => 'user-1'],
            '2026-05-07',
        );

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['body']['ok']);
        $this->assertSame('user-1', $response['body']['daily_log']['user_id']);
        $this->assertSame('2026-05-07', $response['body']['daily_log']['date']);
        $this->assertSame(
            '08:00:00',
            $response['body']['daily_log']['wake_time'],
        );
        $this->assertSame(
            '23:00:00',
            $response['body']['daily_log']['sleep_time'],
        );
        $this->assertSame(1, $this->countDailyLogs());
    }

    public function testTodayDailyLogResponseReturnsExistingLogWithoutDuplicate(): void
    {
        $firstResponse = build_today_daily_log_response(
            $this->pdo,
            ['id' => 'user-1'],
            '2026-05-08',
        );
        $secondResponse = build_today_daily_log_response(
            $this->pdo,
            ['id' => 'user-1'],
            '2026-05-08',
        );

        $this->assertSame(
            $firstResponse['body']['daily_log']['id'],
            $secondResponse['body']['daily_log']['id'],
        );
        $this->assertSame(1, $this->countDailyLogs());
    }

    public function testScheduleResponseReturnsTodayAndFutureDefaults(): void
    {
        build_today_daily_log_response(
            $this->pdo,
            ['id' => 'user-1'],
            '2026-05-09',
        );

        $response = build_daily_log_schedule_response(
            $this->pdo,
            ['id' => 'user-1'],
            '2026-05-09',
        );

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['body']['ok']);
        $this->assertSame(
            '2026-05-09',
            $response['body']['today_daily_log']['date'],
        );
        $this->assertSame(
            '08:00:00',
            $response['body']['future_defaults']['wake_time'],
        );
        $this->assertSame(
            '23:00:00',
            $response['body']['future_defaults']['sleep_time'],
        );
    }

    public function testUpdateScheduleResponsePreservesTodayWakeAndUpdatesFutureDefaults(): void
    {
        build_today_daily_log_response(
            $this->pdo,
            ['id' => 'user-1'],
            '2026-05-10',
        );

        $response = build_update_daily_log_schedule_response(
            $this->pdo,
            ['id' => 'user-1'],
            [
                'wake_time' => '06:45',
                'sleep_time' => '22:05',
            ],
            '2026-05-10',
        );

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['body']['ok']);
        $this->assertSame(
            '08:00:00',
            $response['body']['today_daily_log']['wake_time'],
        );
        $this->assertSame(
            '22:05:00',
            $response['body']['today_daily_log']['sleep_time'],
        );
        $this->assertSame(
            '06:45:00',
            $response['body']['future_defaults']['wake_time'],
        );
        $this->assertSame(
            '22:05:00',
            $response['body']['future_defaults']['sleep_time'],
        );
    }

    private function countDailyLogs(): int
    {
        return (int) $this->pdo
            ->query('SELECT COUNT(*) FROM daily_logs')
            ->fetchColumn();
    }
}
