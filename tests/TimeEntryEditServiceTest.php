<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/daily_logs/helpers.php';
require_once __DIR__ . '/../src/daily_logs/service.php';
require_once __DIR__ . '/../src/time_entries/service.php';
require_once __DIR__ . '/../src/time_entries/add.php';
require_once __DIR__ . '/../src/time_entries/edit.php';

use PHPUnit\Framework\TestCase;

final class TimeEntryEditServiceTest extends TestCase
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

    public function testCompletedEntryEditUpdatesAllFields(): void
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
            '2026-05-21',
            '09:00:00',
            '10:00:00',
            'Initial notes',
            (int) $activity['id'],
        );

        $updated = update_time_entry($this->pdo, 'user-1', (int) $entry['id'], [
            'activity_id' => (int) $activity['id'],
            'activity_subtype_id' => (int) $subtype['id'],
            'start' => '09:15:00',
            'end' => '10:30:00',
            'notes' => 'Updated notes',
        ]);

        $this->assertSame('2026-05-21 09:15:00', $updated['start']);
        $this->assertSame('2026-05-21 10:30:00', $updated['end']);
        $this->assertSame('completed', $updated['state']);
        $this->assertSame(
            (int) $subtype['id'],
            (int) $updated['activity_subtype_id'],
        );
        $this->assertSame('Updated notes', $updated['notes']);
    }

    public function testEditingCompletedEntryPullsPreviousEndBackToNewStart(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Work');

        $previous = add_past_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-21',
            '09:00:00',
            '10:00:00',
            'Previous',
            (int) $activity['id'],
        );
        $current = add_past_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-21',
            '10:00:00',
            '11:00:00',
            'Current',
            (int) $activity['id'],
        );

        update_time_entry($this->pdo, 'user-1', (int) $current['id'], [
            'activity_id' => (int) $activity['id'],
            'start' => '09:30:00',
            'end' => '11:00:00',
            'notes' => 'Current',
        ]);

        $reloadedPrevious = find_time_entry_by_id(
            $this->pdo,
            (int) $previous['id'],
        );
        $this->assertSame('2026-05-21 09:30:00', $reloadedPrevious['end']);
    }

    public function testEditingCompletedEntryPushesNextStartForwardToNewEnd(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Work');

        $current = add_past_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-21',
            '09:00:00',
            '10:00:00',
            'Current',
            (int) $activity['id'],
        );
        $next = add_past_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-21',
            '10:00:00',
            '11:00:00',
            'Next',
            (int) $activity['id'],
        );

        update_time_entry($this->pdo, 'user-1', (int) $current['id'], [
            'activity_id' => (int) $activity['id'],
            'start' => '09:00:00',
            'end' => '10:30:00',
            'notes' => 'Current',
        ]);

        $reloadedNext = find_time_entry_by_id($this->pdo, (int) $next['id']);
        $this->assertSame('2026-05-21 10:30:00', $reloadedNext['start']);
    }

    public function testEditingRunningEntryCanMoveItsStartAndKeepItRunning(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Work');

        add_past_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-21',
            '09:00:00',
            '10:00:00',
            'Previous',
            (int) $activity['id'],
        );
        $running = start_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-21',
            '10:00:00',
        );

        $updated = update_time_entry(
            $this->pdo,
            'user-1',
            (int) $running['id'],
            [
                'activity_id' => (int) $activity['id'],
                'start' => '09:30:00',
                'notes' => 'Still running',
            ],
        );

        $this->assertSame('2026-05-21 09:30:00', $updated['start']);
        $this->assertNull($updated['end']);
        $this->assertSame('running', $updated['state']);
    }

    public function testEditRejectsEntryThatWouldConsumeEntireNextEntry(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Work');

        $current = add_past_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-21',
            '09:00:00',
            '10:00:00',
            'Current',
            (int) $activity['id'],
        );
        $next = add_past_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-21',
            '10:00:00',
            '11:00:00',
            'Next',
            (int) $activity['id'],
        );

        try {
            update_time_entry($this->pdo, 'user-1', (int) $current['id'], [
                'activity_id' => (int) $activity['id'],
                'start' => '09:00:00',
                'end' => '11:00:00',
                'notes' => 'Current',
            ]);
            $this->fail('Expected update to be rejected.');
        } catch (\InvalidArgumentException $exception) {
            $reloadedCurrent = find_time_entry_by_id(
                $this->pdo,
                (int) $current['id'],
            );
            $reloadedNext = find_time_entry_by_id(
                $this->pdo,
                (int) $next['id'],
            );

            $this->assertSame('2026-05-21 10:00:00', $reloadedCurrent['end']);
            $this->assertSame('2026-05-21 10:00:00', $reloadedNext['start']);
        }
    }
}
