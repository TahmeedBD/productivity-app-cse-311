<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/daily_logs/helpers.php';
require_once __DIR__ . '/../src/daily_logs/service.php';
use PHPUnit\Framework\TestCase;

final class DailyLogServiceTest extends TestCase
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

    public function testCreatesMissingDailyLogWithFallbackTimes(): void
    {
        $dailyLog = get_or_create_daily_log($this->pdo, 'user-1', '2026-05-03');

        $this->assertSame('user-1', $dailyLog['user_id']);
        $this->assertSame('2026-05-03', $dailyLog['date']);
        $this->assertSame('08:00:00', $dailyLog['wake_time']);
        $this->assertSame('23:00:00', $dailyLog['sleep_time']);
        $this->assertSame(1, $this->countDailyLogs());
    }

    public function testCreatesMissingDailyLogWithProvidedTimes(): void
    {
        $dailyLog = get_or_create_daily_log(
            $this->pdo,
            'user-1',
            '2026-05-04',
            [
                'wake_time' => '09:00:00',
                'sleep_time' => '22:00:00',
            ],
        );

        $this->assertSame('09:00:00', $dailyLog['wake_time']);
        $this->assertSame('22:00:00', $dailyLog['sleep_time']);
        $this->assertSame(1, $this->countDailyLogs());
    }

    public function testReturnsExistingDailyLogForSameUserAndDateWithoutCreatingDuplicate(): void
    {
        $firstDailyLog = get_or_create_daily_log(
            $this->pdo,
            'user-1',
            '2026-05-05',
        );
        $secondDailyLog = get_or_create_daily_log(
            $this->pdo,
            'user-1',
            '2026-05-05',
        );

        $this->assertSame($firstDailyLog['id'], $secondDailyLog['id']);
        $this->assertSame(1, $this->countDailyLogs());
    }

    public function testDifferentUsersGetSeparateDailyLogsForSameDate(): void
    {
        $firstDailyLog = get_or_create_daily_log(
            $this->pdo,
            'user-1',
            '2026-05-06',
        );
        $secondDailyLog = get_or_create_daily_log(
            $this->pdo,
            'user-2',
            '2026-05-06',
        );

        $this->assertNotSame($firstDailyLog['id'], $secondDailyLog['id']);
        $this->assertSame(2, $this->countDailyLogs());
    }

    public function testCreatesMissingDailyLogUsingMostRecentPreviousTimes(): void
    {
        get_or_create_daily_log($this->pdo, 'user-1', '2026-05-09', [
            'wake_time' => '07:15:00',
            'sleep_time' => '22:10:00',
        ]);

        $dailyLog = get_or_create_daily_log($this->pdo, 'user-1', '2026-05-10');

        $this->assertSame('07:15:00', $dailyLog['wake_time']);
        $this->assertSame('22:10:00', $dailyLog['sleep_time']);
    }

    public function testUpdatingTodaySleepAndFutureTimesPreservesTodayWakeAndSeedsTomorrow(): void
    {
        get_or_create_daily_log($this->pdo, 'user-1', '2026-05-10', [
            'wake_time' => '08:00:00',
            'sleep_time' => '23:00:00',
        ]);
        get_or_create_daily_log($this->pdo, 'user-1', '2026-05-12', [
            'wake_time' => '08:00:00',
            'sleep_time' => '23:00:00',
        ]);

        $result = update_today_sleep_and_future_daily_log_times(
            $this->pdo,
            'user-1',
            '2026-05-10',
            '06:30:00',
            '22:15:00',
        );

        $this->assertSame('08:00:00', $result['today_daily_log']['wake_time']);
        $this->assertSame('22:15:00', $result['today_daily_log']['sleep_time']);
        $this->assertSame('06:30:00', $result['future_defaults']['wake_time']);
        $this->assertSame('22:15:00', $result['future_defaults']['sleep_time']);

        $tomorrow = find_daily_log_by_user_and_date(
            $this->pdo,
            'user-1',
            '2026-05-11',
        );
        $later = find_daily_log_by_user_and_date(
            $this->pdo,
            'user-1',
            '2026-05-12',
        );
        $futureCreated = get_or_create_daily_log(
            $this->pdo,
            'user-1',
            '2026-05-13',
        );

        $this->assertNotNull($tomorrow);
        $this->assertSame('06:30:00', $tomorrow['wake_time']);
        $this->assertSame('22:15:00', $tomorrow['sleep_time']);
        $this->assertNotNull($later);
        $this->assertSame('06:30:00', $later['wake_time']);
        $this->assertSame('22:15:00', $later['sleep_time']);
        $this->assertSame('06:30:00', $futureCreated['wake_time']);
        $this->assertSame('22:15:00', $futureCreated['sleep_time']);
    }

    private function countDailyLogs(): int
    {
        return (int) $this->pdo
            ->query('SELECT COUNT(*) FROM daily_logs')
            ->fetchColumn();
    }
}
