<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/daily_logs/helpers.php';
require_once __DIR__ . '/../src/daily_logs/service.php';
require_once __DIR__ . '/../src/time_entries/service.php';

use PHPUnit\Framework\TestCase;

final class TimeEntryAddPastServiceTest extends TestCase
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

    public function testAddsPastEntryAsCompleted(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Work');

        $entry = add_past_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-17',
            '09:00:00',
            '10:30:00',
            'Morning focus',
            (int) $activity['id'],
        );

        $this->assertSame('2026-05-17 09:00:00', $entry['start']);
        $this->assertSame('2026-05-17 10:30:00', $entry['end']);
        $this->assertSame('completed', $entry['state']);
        $this->assertSame('Morning focus', $entry['notes']);
    }

    public function testRejectsEntryWithStartBeforeWakeTime(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Work');

        $this->expectException(\InvalidArgumentException::class);

        add_past_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-17',
            '07:30:00',
            '08:30:00',
            'Too early',
            (int) $activity['id'],
        );
    }

    public function testRejectsEntryWithEndAfterSleepTime(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Work');

        $this->expectException(\InvalidArgumentException::class);

        add_past_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-17',
            '22:00:00',
            '23:30:00',
            'Too late',
            (int) $activity['id'],
        );
    }

    public function testRejectsEntryThatOverlapsExistingCompletedEntry(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Work');

        add_past_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-17',
            '09:00:00',
            '10:00:00',
            'First block',
            (int) $activity['id'],
        );

        $this->expectException(\InvalidArgumentException::class);

        add_past_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-17',
            '09:30:00',
            '10:30:00',
            'Overlapping',
            (int) $activity['id'],
        );
    }

    public function testRejectsEntryThatOverlapsRunningEntry(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Work');

        // Running entry starts at 09:00 with no end
        start_time_entry($this->pdo, 'user-1', '2026-05-17', '09:00:00');

        $this->expectException(\InvalidArgumentException::class);

        // Starts after the running entry's start → overlaps
        add_past_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-17',
            '09:30:00',
            '10:30:00',
            'Overlapping with running',
            (int) $activity['id'],
        );
    }

    public function testRejectsEntryWhoseEndOverlapsRunningEntry(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Work');

        // Running entry starts at 10:00
        start_time_entry($this->pdo, 'user-1', '2026-05-17', '10:00:00');

        $this->expectException(\InvalidArgumentException::class);

        // End time (10:30) is after running entry's start (10:00) → overlaps
        add_past_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-17',
            '09:00:00',
            '10:30:00',
            'Overlapping end',
            (int) $activity['id'],
        );
    }

    public function testAllowsPastEntryEndingExactlyWhenRunningEntryStarts(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Work');

        // Running entry starts at 10:00
        start_time_entry($this->pdo, 'user-1', '2026-05-17', '10:00:00');

        // Past entry ends at exactly 10:00 — adjacent but not overlapping
        $entry = add_past_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-17',
            '09:00:00',
            '10:00:00',
            'Adjacent',
            (int) $activity['id'],
        );

        $this->assertSame('completed', $entry['state']);
        $this->assertSame(2, $this->countEntries());
    }

    public function testRunningEntryRemainsRunningAfterAddingPastEntry(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Work');

        $runningEntry = start_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-17',
            '11:00:00',
        );

        add_past_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-17',
            '09:00:00',
            '10:30:00',
            'Gap filler',
            (int) $activity['id'],
        );

        $reloaded = $this->findEntryById((int) $runningEntry['id']);
        $this->assertNull($reloaded['end']);
        $this->assertSame('running', $reloaded['state']);
    }

    public function testAllowsPastEntryBeforeRunningEntryWithoutOverlap(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Work');

        start_time_entry($this->pdo, 'user-1', '2026-05-17', '11:00:00');

        $entry = add_past_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-17',
            '09:00:00',
            '10:30:00',
            'Gap filler',
            (int) $activity['id'],
        );

        $this->assertSame('completed', $entry['state']);
        $this->assertSame(2, $this->countEntries());
    }

    public function testAddPastEntryPersistsSelectedActivityAndSubtype(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Work');
        $subtype = create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'Planning',
        );

        $entry = add_past_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-17',
            '09:00:00',
            '10:30:00',
            'Morning focus',
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
            'SELECT id, daily_log_id, activity_id, activity_subtype_id, start, end, state, notes
             FROM time_entries WHERE id = :id',
        );
        $statement->execute([':id' => $entryId]);

        return $statement->fetch();
    }
}
