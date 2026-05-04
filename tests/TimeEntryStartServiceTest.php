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

    public function testStartsFirstEntryAsRunningWhenNoEntriesExist(): void
    {
        $entry = start_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-09',
            '09:00:00',
            'Deep work',
        );

        $this->assertSame('2026-05-09 09:00:00', $entry['start']);
        $this->assertNull($entry['end']);
        $this->assertSame('running', $entry['state']);
        $this->assertSame('Deep work', $entry['notes']);
        $this->assertSame(1, $this->countEntries());
    }

    public function testStartingNewEntryClosesPreviousRunningEntryAtExactNewStart(): void
    {
        $firstEntry = start_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-09',
            '09:00:00',
            'First block',
        );
        $secondEntry = start_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-09',
            '10:15:00',
            'Second block',
        );

        $reloadedFirstEntry = $this->findEntryById((int) $firstEntry['id']);

        $this->assertSame('2026-05-09 10:15:00', $reloadedFirstEntry['end']);
        $this->assertSame('completed', $reloadedFirstEntry['state']);
        $this->assertNull($secondEntry['end']);
        $this->assertSame('running', $secondEntry['state']);
        $this->assertSame(2, $this->countEntries());
    }

    public function testRejectsEntryStartBeforeWakeTime(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        start_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-09',
            '07:30:00',
            'Too early',
        );
    }

    public function testRejectsEntryStartAtOrAfterSleepTime(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        start_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-09',
            '23:00:00',
            'Too late',
        );
    }

    public function testRejectsNewStartThatDoesNotAdvancePastCurrentRunningEntry(): void
    {
        start_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-09',
            '09:00:00',
            'First block',
        );

        $this->expectException(\InvalidArgumentException::class);

        start_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-09',
            '09:00:00',
            'Invalid second block',
        );
    }

    public function testStartPersistsSelectedActivityAndSubtype(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Work');
        $subtype = create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'Coding',
        );

        $entry = start_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-09',
            '09:00:00',
            'Deep work',
            (int) $activity['id'],
            (int) $subtype['id'],
        );

        $this->assertSame((int) $activity['id'], (int) $entry['activity_id']);
        $this->assertSame(
            (int) $subtype['id'],
            (int) $entry['activity_subtype_id'],
        );
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
