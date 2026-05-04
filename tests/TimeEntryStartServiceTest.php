<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/daily_logs/helpers.php';
require_once __DIR__ . '/../src/daily_logs/service.php';
require_once __DIR__ . '/../src/time_entries/service.php';

use PHPUnit\Framework\TestCase;

final class TimeEntryStartServiceTest extends TestCase
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

    public function testStartsFirstEntryAsBlankRunningEntryWhenNoEntriesExist(): void
    {
        $entry = start_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-09',
            '09:00:00',
        );

        $this->assertSame('2026-05-09 09:00:00', $entry['start']);
        $this->assertNull($entry['end']);
        $this->assertSame('running', $entry['state']);
        $this->assertSame('', $entry['notes']);
        $this->assertNull($entry['activity_id']);
        $this->assertNull($entry['activity_subtype_id']);
        $this->assertSame(1, $this->countEntries());
    }

    public function testStopAndStartPersistsCurrentEntryValuesAndCreatesBlankNextEntry(): void
    {
        $firstEntry = start_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-09',
            '09:00:00',
        );
        $activity = create_activity($this->pdo, 'user-1', 'Work');
        $subtype = create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'Coding',
        );
        $secondEntry = start_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-09',
            '10:15:00',
            [
                'activity_id' => (int) $activity['id'],
                'activity_subtype_id' => (int) $subtype['id'],
                'notes' => 'First block',
            ],
        );

        $reloadedFirstEntry = $this->findEntryById((int) $firstEntry['id']);

        $this->assertSame('2026-05-09 10:15:00', $reloadedFirstEntry['end']);
        $this->assertSame('completed', $reloadedFirstEntry['state']);
        $this->assertSame(
            (int) $activity['id'],
            (int) $reloadedFirstEntry['activity_id'],
        );
        $this->assertSame(
            (int) $subtype['id'],
            (int) $reloadedFirstEntry['activity_subtype_id'],
        );
        $this->assertSame('First block', $reloadedFirstEntry['notes']);
        $this->assertNull($secondEntry['end']);
        $this->assertSame('running', $secondEntry['state']);
        $this->assertNull($secondEntry['activity_id']);
        $this->assertNull($secondEntry['activity_subtype_id']);
        $this->assertSame('', $secondEntry['notes']);
        $this->assertSame(2, $this->countEntries());
    }

    public function testRejectsEntryStartBeforeWakeTime(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        start_time_entry($this->pdo, 'user-1', '2026-05-09', '07:30:00', []);
    }

    public function testRejectsEntryStartAtOrAfterSleepTime(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        start_time_entry($this->pdo, 'user-1', '2026-05-09', '23:00:00', []);
    }

    public function testRejectsNewStartThatDoesNotAdvancePastCurrentRunningEntry(): void
    {
        start_time_entry($this->pdo, 'user-1', '2026-05-09', '09:00:00');

        $this->expectException(\InvalidArgumentException::class);

        start_time_entry($this->pdo, 'user-1', '2026-05-09', '09:00:00');
    }

    public function testRejectsStopAndStartWhenActivityIsMissing(): void
    {
        start_time_entry($this->pdo, 'user-1', '2026-05-09', '09:00:00');

        $this->expectException(\InvalidArgumentException::class);

        start_time_entry($this->pdo, 'user-1', '2026-05-09', '10:15:00', [
            'notes' => 'Missing activity',
        ]);
    }

    public function testRejectsStopAndStartWhenSubtypeDoesNotBelongToActivity(): void
    {
        start_time_entry($this->pdo, 'user-1', '2026-05-09', '09:00:00');
        $activity = create_activity($this->pdo, 'user-1', 'Work');
        $otherActivity = create_activity($this->pdo, 'user-1', 'Health');
        $subtype = create_activity_subtype(
            $this->pdo,
            (int) $otherActivity['id'],
            'user-1',
            'Cardio',
        );

        $this->expectException(\InvalidArgumentException::class);

        start_time_entry($this->pdo, 'user-1', '2026-05-09', '10:15:00', [
            'activity_id' => (int) $activity['id'],
            'activity_subtype_id' => (int) $subtype['id'],
        ]);
    }

    private function countEntries(): int
    {
        return (int) $this->pdo
            ->query('SELECT COUNT(*) FROM time_entries')
            ->fetchColumn();
    }

    private function findEntryById(int $entryId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, daily_log_id, activity_id, activity_subtype_id, start, end, state, notes FROM time_entries WHERE id = :id',
        );
        $statement->execute([':id' => $entryId]);

        return $statement->fetch();
    }
}
