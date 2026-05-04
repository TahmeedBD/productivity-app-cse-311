<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/activities/service.php';

use PHPUnit\Framework\TestCase;

final class ActivityServiceTest extends TestCase
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
                name TEXT NOT NULL,
                UNIQUE(activity_id, name)
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

    // ── Create ───────────────────────────────────────────────

    public function testCreatesActivity(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Reading');

        $this->assertSame('Reading', $activity['name']);
        $this->assertSame('user-1', $activity['user_id']);
        $this->assertNotEmpty($activity['id']);
    }

    public function testRejectsBlankActivityName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        create_activity($this->pdo, 'user-1', '   ');
    }

    public function testRejectsActivityNameExceedingMaxLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        create_activity($this->pdo, 'user-1', str_repeat('x', 101));
    }

    public function testRejectsDuplicateActivityNameForSameUser(): void
    {
        create_activity($this->pdo, 'user-1', 'Reading');

        $this->expectException(\InvalidArgumentException::class);

        create_activity($this->pdo, 'user-1', 'Reading');
    }

    public function testDuplicateNameIsAllowedAcrossDifferentUsers(): void
    {
        create_activity($this->pdo, 'user-1', 'Reading');
        $activity = create_activity($this->pdo, 'user-2', 'Reading');

        $this->assertSame('Reading', $activity['name']);
        $this->assertSame('user-2', $activity['user_id']);
    }

    // ── List ─────────────────────────────────────────────────

    public function testListsActivitiesAlphabetically(): void
    {
        create_activity($this->pdo, 'user-1', 'Work');
        create_activity($this->pdo, 'user-1', 'Exercise');
        create_activity($this->pdo, 'user-1', 'Reading');

        $activities = list_activities($this->pdo, 'user-1');

        $this->assertCount(3, $activities);
        $this->assertSame('Exercise', $activities[0]['name']);
        $this->assertSame('Reading', $activities[1]['name']);
        $this->assertSame('Work', $activities[2]['name']);
    }

    public function testListActivitiesReturnsEmptyArrayWhenNoneExist(): void
    {
        $this->assertSame([], list_activities($this->pdo, 'user-1'));
    }

    public function testListActivitiesDoesNotLeakOtherUsersActivities(): void
    {
        create_activity($this->pdo, 'user-1', 'Mine');
        create_activity($this->pdo, 'user-2', 'Not mine');

        $activities = list_activities($this->pdo, 'user-1');

        $this->assertCount(1, $activities);
        $this->assertSame('Mine', $activities[0]['name']);
    }

    // ── Update ───────────────────────────────────────────────

    public function testUpdatesActivityName(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Old name');

        $updated = update_activity(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'New name',
        );

        $this->assertSame('New name', $updated['name']);
    }

    public function testUpdateActivityToSameNameSucceeds(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Reading');

        $updated = update_activity(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'Reading',
        );

        $this->assertSame('Reading', $updated['name']);
    }

    public function testUpdateActivityRejectsNameAlreadyUsedByAnotherActivity(): void
    {
        $a = create_activity($this->pdo, 'user-1', 'First');
        create_activity($this->pdo, 'user-1', 'Second');

        $this->expectException(\InvalidArgumentException::class);

        update_activity($this->pdo, (int) $a['id'], 'user-1', 'Second');
    }

    public function testUpdateActivityThrowsWhenActivityNotOwnedByUser(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Private');

        $this->expectException(\InvalidArgumentException::class);

        update_activity(
            $this->pdo,
            (int) $activity['id'],
            'user-2',
            'Hijacked',
        );
    }

    public function testUpdateActivityRejectsBlankName(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Reading');

        $this->expectException(\InvalidArgumentException::class);

        update_activity($this->pdo, (int) $activity['id'], 'user-1', '');
    }

    // ── Delete ───────────────────────────────────────────────

    public function testDeletesActivity(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Temporary');

        delete_activity($this->pdo, (int) $activity['id'], 'user-1');

        $this->assertSame([], list_activities($this->pdo, 'user-1'));
    }

    public function testDeleteActivityThrowsWhenActivityNotOwnedByUser(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Private');

        $this->expectException(\InvalidArgumentException::class);

        delete_activity($this->pdo, (int) $activity['id'], 'user-2');
    }

    public function testDeleteActivityIsBlockedWhenActivityHasTimeEntries(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Work');

        $this->pdo->exec(
            "INSERT INTO time_entries (daily_log_id, activity_id, start, state, notes)
             VALUES (1, {$activity['id']}, '2026-05-20 09:00:00', 'completed', '')",
        );

        $this->expectException(\InvalidArgumentException::class);

        delete_activity($this->pdo, (int) $activity['id'], 'user-1');
    }
}
