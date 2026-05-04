<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/activities/service.php';
require_once __DIR__ . '/../src/activities/create.php';
require_once __DIR__ . '/../src/activities/list.php';
require_once __DIR__ . '/../src/activities/update.php';
require_once __DIR__ . '/../src/activities/delete.php';

use PHPUnit\Framework\TestCase;

final class ActivityEndpointTest extends TestCase
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

    public function testCreateActivityReturns201WithCreatedActivity(): void
    {
        $response = build_create_activity_response(
            $this->pdo,
            ['id' => 'user-1'],
            ['name' => 'Reading'],
        );

        $this->assertSame(201, $response['status']);
        $this->assertTrue($response['body']['ok']);
        $this->assertSame('Reading', $response['body']['activity']['name']);
        $this->assertSame('user-1', $response['body']['activity']['user_id']);
    }

    public function testCreateActivityReturns422WhenNameIsBlank(): void
    {
        $response = build_create_activity_response(
            $this->pdo,
            ['id' => 'user-1'],
            ['name' => ''],
        );

        $this->assertSame(422, $response['status']);
        $this->assertFalse($response['body']['ok']);
        $this->assertArrayHasKey('error', $response['body']);
    }

    public function testCreateActivityReturns422WhenNameIsMissing(): void
    {
        $response = build_create_activity_response(
            $this->pdo,
            ['id' => 'user-1'],
            [],
        );

        $this->assertSame(422, $response['status']);
        $this->assertFalse($response['body']['ok']);
    }

    public function testCreateActivityReturns409WhenNameAlreadyExists(): void
    {
        create_activity($this->pdo, 'user-1', 'Reading');

        $response = build_create_activity_response(
            $this->pdo,
            ['id' => 'user-1'],
            ['name' => 'Reading'],
        );

        $this->assertSame(409, $response['status']);
        $this->assertFalse($response['body']['ok']);
    }

    // ── List ─────────────────────────────────────────────────

    public function testListActivitiesReturns200WithActivities(): void
    {
        create_activity($this->pdo, 'user-1', 'Work');
        create_activity($this->pdo, 'user-1', 'Leisure');

        $response = build_list_activities_response($this->pdo, [
            'id' => 'user-1',
        ]);

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['body']['ok']);
        $this->assertCount(2, $response['body']['activities']);
    }

    public function testListActivitiesReturns200WithEmptyArrayWhenNoneExist(): void
    {
        $response = build_list_activities_response($this->pdo, [
            'id' => 'user-1',
        ]);

        $this->assertSame(200, $response['status']);
        $this->assertSame([], $response['body']['activities']);
    }

    // ── Update ───────────────────────────────────────────────

    public function testUpdateActivityReturns200WithUpdatedActivity(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Old name');

        $response = build_update_activity_response(
            $this->pdo,
            ['id' => 'user-1'],
            ['id' => $activity['id'], 'name' => 'New name'],
        );

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['body']['ok']);
        $this->assertSame('New name', $response['body']['activity']['name']);
    }

    public function testUpdateActivityReturns409WhenNameConflicts(): void
    {
        $a = create_activity($this->pdo, 'user-1', 'First');
        create_activity($this->pdo, 'user-1', 'Second');

        $response = build_update_activity_response(
            $this->pdo,
            ['id' => 'user-1'],
            ['id' => $a['id'], 'name' => 'Second'],
        );

        $this->assertSame(409, $response['status']);
        $this->assertFalse($response['body']['ok']);
    }

    public function testUpdateActivityReturns422WhenIdIsMissing(): void
    {
        $response = build_update_activity_response(
            $this->pdo,
            ['id' => 'user-1'],
            ['name' => 'No id provided'],
        );

        $this->assertSame(422, $response['status']);
        $this->assertFalse($response['body']['ok']);
    }

    // ── Delete ───────────────────────────────────────────────

    public function testDeleteActivityReturns200OnSuccess(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Temporary');

        $response = build_delete_activity_response(
            $this->pdo,
            ['id' => 'user-1'],
            ['id' => $activity['id']],
        );

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['body']['ok']);
        $this->assertSame([], list_activities($this->pdo, 'user-1'));
    }

    public function testDeleteActivityReturns409WhenActivityHasTimeEntries(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Work');

        $this->pdo->exec(
            "INSERT INTO time_entries (daily_log_id, activity_id, start, state, notes)
             VALUES (1, {$activity['id']}, '2026-05-20 09:00:00', 'completed', '')",
        );

        $response = build_delete_activity_response(
            $this->pdo,
            ['id' => 'user-1'],
            ['id' => $activity['id']],
        );

        $this->assertSame(409, $response['status']);
        $this->assertFalse($response['body']['ok']);
    }

    public function testDeleteActivityReturns422WhenIdIsMissing(): void
    {
        $response = build_delete_activity_response(
            $this->pdo,
            ['id' => 'user-1'],
            [],
        );

        $this->assertSame(422, $response['status']);
        $this->assertFalse($response['body']['ok']);
    }
}
