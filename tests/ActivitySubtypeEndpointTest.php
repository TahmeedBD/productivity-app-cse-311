<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/activities/service.php';
require_once __DIR__ . '/../src/activity_subtypes/service.php';
require_once __DIR__ . '/../src/activity_subtypes/create.php';
require_once __DIR__ . '/../src/activity_subtypes/list.php';
require_once __DIR__ . '/../src/activity_subtypes/update.php';
require_once __DIR__ . '/../src/activity_subtypes/delete.php';

use PHPUnit\Framework\TestCase;

final class ActivitySubtypeEndpointTest extends TestCase
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

    public function testCreateSubtypeReturns201WithCreatedSubtype(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Reading');

        $response = build_create_activity_subtype_response(
            $this->pdo,
            ['id' => 'user-1'],
            ['activity_id' => $activity['id'], 'name' => 'Fiction'],
        );

        $this->assertSame(201, $response['status']);
        $this->assertTrue($response['body']['ok']);
        $this->assertSame('Fiction', $response['body']['subtype']['name']);
    }

    public function testCreateSubtypeReturns422WhenNameIsBlank(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Reading');

        $response = build_create_activity_subtype_response(
            $this->pdo,
            ['id' => 'user-1'],
            ['activity_id' => $activity['id'], 'name' => ''],
        );

        $this->assertSame(422, $response['status']);
        $this->assertFalse($response['body']['ok']);
    }

    public function testCreateSubtypeReturns422WhenActivityIdIsMissing(): void
    {
        $response = build_create_activity_subtype_response(
            $this->pdo,
            ['id' => 'user-1'],
            ['name' => 'Fiction'],
        );

        $this->assertSame(422, $response['status']);
        $this->assertFalse($response['body']['ok']);
    }

    public function testCreateSubtypeReturns409WhenNameAlreadyExistsInActivity(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Reading');
        create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'Fiction',
        );

        $response = build_create_activity_subtype_response(
            $this->pdo,
            ['id' => 'user-1'],
            ['activity_id' => $activity['id'], 'name' => 'Fiction'],
        );

        $this->assertSame(409, $response['status']);
        $this->assertFalse($response['body']['ok']);
    }

    // ── List ─────────────────────────────────────────────────

    public function testListSubtypesReturns200WithSubtypes(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Reading');
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
            'Non-fiction',
        );

        $response = build_list_activity_subtypes_response(
            $this->pdo,
            ['id' => 'user-1'],
            ['activity_id' => $activity['id']],
        );

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['body']['ok']);
        $this->assertCount(2, $response['body']['subtypes']);
    }

    // ── Update ───────────────────────────────────────────────

    public function testUpdateSubtypeReturns200WithUpdatedSubtype(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Reading');
        $subtype = create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'Old',
        );

        $response = build_update_activity_subtype_response(
            $this->pdo,
            ['id' => 'user-1'],
            [
                'id' => $subtype['id'],
                'activity_id' => $activity['id'],
                'name' => 'New',
            ],
        );

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['body']['ok']);
        $this->assertSame('New', $response['body']['subtype']['name']);
    }

    public function testUpdateSubtypeReturns422WhenIdIsMissing(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Reading');

        $response = build_update_activity_subtype_response(
            $this->pdo,
            ['id' => 'user-1'],
            ['activity_id' => $activity['id'], 'name' => 'New'],
        );

        $this->assertSame(422, $response['status']);
        $this->assertFalse($response['body']['ok']);
    }

    // ── Delete ───────────────────────────────────────────────

    public function testDeleteSubtypeReturns200OnSuccess(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Reading');
        $subtype = create_activity_subtype(
            $this->pdo,
            (int) $activity['id'],
            'user-1',
            'Temp',
        );

        $response = build_delete_activity_subtype_response(
            $this->pdo,
            ['id' => 'user-1'],
            ['id' => $subtype['id'], 'activity_id' => $activity['id']],
        );

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['body']['ok']);
    }

    public function testDeleteSubtypeReturns409WhenSubtypeHasTimeEntries(): void
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

        $response = build_delete_activity_subtype_response(
            $this->pdo,
            ['id' => 'user-1'],
            ['id' => $subtype['id'], 'activity_id' => $activity['id']],
        );

        $this->assertSame(409, $response['status']);
        $this->assertFalse($response['body']['ok']);
    }
}
