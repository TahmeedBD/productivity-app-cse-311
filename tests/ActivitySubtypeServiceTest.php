<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/activities/service.php';
require_once __DIR__ . '/../src/activity_subtypes/service.php';

use PHPUnit\Framework\TestCase;

final class ActivitySubtypeServiceTest extends TestCase
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

    public function testCreatesSubtype(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Reading');

        $subtype = create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'Non-fiction',
        );

        $this->assertSame('Non-fiction', $subtype['name']);
        $this->assertSame($activity['id'], $subtype['activity_id']);
        $this->assertNotEmpty($subtype['id']);
    }

    public function testRejectsBlankSubtypeName(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Reading');

        $this->expectException(\InvalidArgumentException::class);

        create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            '',
        );
    }

    public function testRejectsSubtypeNameExceedingMaxLength(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Reading');

        $this->expectException(\InvalidArgumentException::class);

        create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            str_repeat('x', 101),
        );
    }

    public function testRejectsDuplicateSubtypeNameWithinSameActivity(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Reading');
        create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'Non-fiction',
        );

        $this->expectException(\InvalidArgumentException::class);

        create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'Non-fiction',
        );
    }

    public function testSameSubtypeNameAllowedInDifferentActivities(): void
    {
        $actA = create_activity($this->pdo, 'user-1', 'Reading');
        $actB = create_activity($this->pdo, 'user-1', 'Coding');

        create_activity_subtype(
            $this->pdo,
            (int) $actA['id'],
            'user-1',
            'Deep work',
        );
        $subtype = create_activity_subtype(
            $this->pdo,
            (int) $actB['id'],
            'user-1',
            'Deep work',
        );

        $this->assertSame('Deep work', $subtype['name']);
    }

    public function testCreateSubtypeThrowsWhenActivityNotOwnedByUser(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Private activity');

        $this->expectException(\InvalidArgumentException::class);

        create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-2',
            'Hijacked subtype',
        );
    }

    // ── List ─────────────────────────────────────────────────

    public function testListsSubtypesAlphabetically(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Reading');
        create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'Technical',
        );
        create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'Fiction',
        );
        create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'Biography',
        );

        $subtypes = list_activity_subtypes(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
        );

        $this->assertCount(3, $subtypes);
        $this->assertSame('Biography', $subtypes[0]['name']);
        $this->assertSame('Fiction', $subtypes[1]['name']);
        $this->assertSame('Technical', $subtypes[2]['name']);
    }

    public function testListSubtypesReturnsEmptyArrayWhenNoneExist(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Reading');

        $this->assertSame(
            [],
            list_activity_subtypes($this->pdo, (int) $activity['id'], 'user-1'),
        );
    }

    public function testListSubtypesThrowsWhenActivityNotOwnedByUser(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Private');

        $this->expectException(\InvalidArgumentException::class);

        list_activity_subtypes($this->pdo, (int) $activity['id'], 'user-2');
    }

    // ── Update ───────────────────────────────────────────────

    public function testUpdatesSubtypeName(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Reading');
        $subtype = create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'Old',
        );

        $updated = update_activity_subtype(
            $this->pdo,
            (int) $subtype['id'],
            (int) $activity['id'],
            'user-1',
            'New',
        );

        $this->assertSame('New', $updated['name']);
    }

    public function testUpdateSubtypeToSameNameSucceeds(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Reading');
        $subtype = create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'Fiction',
        );

        $updated = update_activity_subtype(
            $this->pdo,
            (int) $subtype['id'],
            (int) $activity['id'],
            'user-1',
            'Fiction',
        );

        $this->assertSame('Fiction', $updated['name']);
    }

    public function testUpdateSubtypeRejectsNameAlreadyUsedByAnotherSubtype(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Reading');
        $s1 = create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'First',
        );
        create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'Second',
        );

        $this->expectException(\InvalidArgumentException::class);

        update_activity_subtype(
            $this->pdo,
            (int) $s1['id'],
            (int) $activity['id'],
            'user-1',
            'Second',
        );
    }

    public function testUpdateSubtypeThrowsWhenActivityNotOwnedByUser(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Reading');
        $subtype = create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'Fiction',
        );

        $this->expectException(\InvalidArgumentException::class);

        update_activity_subtype(
            $this->pdo,
            (int) $subtype['id'],
            (int) $activity['id'],
            'user-2',
            'Hijacked',
        );
    }

    // ── Delete ───────────────────────────────────────────────

    public function testDeletesSubtype(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Reading');
        $subtype = create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'Temp',
        );

        delete_activity_subtype(
            $this->pdo,
            (int) $subtype['id'],
            (int) $activity['id'],
            'user-1',
        );

        $this->assertSame(
            [],
            list_activity_subtypes($this->pdo, (int) $activity['id'], 'user-1'),
        );
    }

    public function testDeleteSubtypeThrowsWhenActivityNotOwnedByUser(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Reading');
        $subtype = create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'Fiction',
        );

        $this->expectException(\InvalidArgumentException::class);

        delete_activity_subtype(
            $this->pdo,
            (int) $subtype['id'],
            (int) $activity['id'],
            'user-2',
        );
    }

    public function testDeleteSubtypeIsBlockedWhenItHasTimeEntries(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Reading');
        $subtype = create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'Fiction',
        );

        $this->pdo->exec(
            "INSERT INTO time_entries (daily_log_id, activity_id, activity_subtype_id, start, state, notes)
             VALUES (1, {$activity['id']}, {$subtype['id']}, '2026-05-20 09:00:00', 'completed', '')",
        );

        $this->expectException(\InvalidArgumentException::class);

        delete_activity_subtype(
            $this->pdo,
            (int) $subtype['id'],
            (int) $activity['id'],
            'user-1',
        );
    }
}
