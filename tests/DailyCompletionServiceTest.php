<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/activities/service.php';
require_once __DIR__ . '/../src/daily_logs/helpers.php';
require_once __DIR__ . '/../src/daily_logs/service.php';
require_once __DIR__ . '/../src/time_entries/service.php';
require_once __DIR__ . '/../src/time_entries/start.php';
require_once __DIR__ . '/../src/time_entries/end.php';
require_once __DIR__ . '/../src/time_entries/edit.php';
require_once __DIR__ . '/../src/time_entries/delete.php';
require_once __DIR__ . '/../src/checklist_items/service.php';
require_once __DIR__ . '/../src/daily_completions/service.php';

use PHPUnit\Framework\TestCase;

final class DailyCompletionServiceTest extends TestCase
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
            'CREATE TABLE activity_subtypes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                activity_id INTEGER NOT NULL,
                name TEXT NOT NULL
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

    public function testEndingEntryMarksMatchingChecklistItemCompleteWhenDurationQualifies(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Coding');
        create_checklist_item($this->pdo, 'user-1', (int) $activity['id'], 20);

        start_time_entry($this->pdo, 'user-1', '2026-05-24', '09:00:00');
        end_time_entry($this->pdo, 'user-1', '2026-05-24', '09:30:00', [
            'activity_id' => (int) $activity['id'],
        ]);

        $this->assertSame(1, $this->countDailyCompletions());
    }

    public function testEndingEntryDoesNotMarkChecklistItemWhenDurationTooShort(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Coding');
        create_checklist_item($this->pdo, 'user-1', (int) $activity['id'], 40);

        start_time_entry($this->pdo, 'user-1', '2026-05-24', '09:00:00');
        end_time_entry($this->pdo, 'user-1', '2026-05-24', '09:30:00', [
            'activity_id' => (int) $activity['id'],
        ]);

        $this->assertSame(0, $this->countDailyCompletions());
    }

    public function testStartingNewEntryMarksPreviousRunningEntryCompleteWhenItQualifies(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Coding');
        create_checklist_item($this->pdo, 'user-1', (int) $activity['id'], 20);

        start_time_entry($this->pdo, 'user-1', '2026-05-24', '09:00:00');
        start_time_entry($this->pdo, 'user-1', '2026-05-24', '09:30:00', [
            'activity_id' => (int) $activity['id'],
        ]);

        $this->assertSame(1, $this->countDailyCompletions());
    }

    public function testOnlyOneCompletionRowIsStoredPerChecklistItemPerDay(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Coding');
        create_checklist_item($this->pdo, 'user-1', (int) $activity['id'], 20);

        start_time_entry($this->pdo, 'user-1', '2026-05-24', '09:00:00');
        end_time_entry($this->pdo, 'user-1', '2026-05-24', '09:30:00', [
            'activity_id' => (int) $activity['id'],
        ]);

        start_time_entry($this->pdo, 'user-1', '2026-05-24', '10:00:00');
        end_time_entry($this->pdo, 'user-1', '2026-05-24', '10:30:00', [
            'activity_id' => (int) $activity['id'],
        ]);

        $this->assertSame(1, $this->countDailyCompletions());
    }

    public function testEditingOrDeletingPastEntriesDoesNotUndoExistingCompletionRows(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Coding');
        create_checklist_item($this->pdo, 'user-1', (int) $activity['id'], 20);

        start_time_entry($this->pdo, 'user-1', '2026-05-24', '09:00:00');
        $ended = end_time_entry(
            $this->pdo,
            'user-1',
            '2026-05-24',
            '09:30:00',
            [
                'activity_id' => (int) $activity['id'],
            ],
        );

        update_time_entry($this->pdo, 'user-1', (int) $ended['id'], [
            'activity_id' => (int) $activity['id'],
            'start' => '09:10:00',
            'end' => '09:25:00',
        ]);
        delete_time_entry($this->pdo, 'user-1', (int) $ended['id']);

        $this->assertSame(1, $this->countDailyCompletions());
    }

    public function testUpdatingMinimumDurationDoesNotRecalculateExistingCompletions(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Coding');
        $item = create_checklist_item(
            $this->pdo,
            'user-1',
            (int) $activity['id'],
            20,
        );

        start_time_entry($this->pdo, 'user-1', '2026-05-24', '09:00:00');
        end_time_entry($this->pdo, 'user-1', '2026-05-24', '09:30:00', [
            'activity_id' => (int) $activity['id'],
        ]);

        update_checklist_item(
            $this->pdo,
            (int) $item['id'],
            'user-1',
            (int) $activity['id'],
            60,
        );

        $this->assertSame(1, $this->countDailyCompletions());
    }

    public function testListsCompletionSummariesWithCompletionDates(): void
    {
        $coding = create_activity($this->pdo, 'user-1', 'Coding');
        $reading = create_activity($this->pdo, 'user-1', 'Reading');
        $codingItem = create_checklist_item(
            $this->pdo,
            'user-1',
            (int) $coding['id'],
            20,
        );
        $readingItem = create_checklist_item(
            $this->pdo,
            'user-1',
            (int) $reading['id'],
            20,
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
            (int) $codingItem['id'],
        );
        mark_checklist_item_complete_for_day(
            $this->pdo,
            'user-1',
            (int) $secondLog['id'],
            (int) $codingItem['id'],
        );
        mark_checklist_item_complete_for_day(
            $this->pdo,
            'user-1',
            (int) $secondLog['id'],
            (int) $readingItem['id'],
        );

        $summaries = list_daily_completion_summaries($this->pdo, 'user-1');

        $this->assertCount(2, $summaries);
        $this->assertSame('Coding', $summaries[0]['activity_name']);
        $this->assertSame(2, $summaries[0]['completed_days_count']);
        $this->assertSame(
            ['2026-05-23', '2026-05-24'],
            $summaries[0]['completion_dates'],
        );
        $this->assertSame('Reading', $summaries[1]['activity_name']);
        $this->assertSame(['2026-05-24'], $summaries[1]['completion_dates']);
    }

    private function countDailyCompletions(): int
    {
        return (int) $this->pdo
            ->query('SELECT COUNT(*) FROM daily_completions')
            ->fetchColumn();
    }
}
