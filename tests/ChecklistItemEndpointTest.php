<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/activities/service.php';
require_once __DIR__ . '/../src/checklist_items/service.php';
require_once __DIR__ . '/../src/checklist_items/create.php';
require_once __DIR__ . '/../src/checklist_items/list.php';
require_once __DIR__ . '/../src/checklist_items/update.php';

use PHPUnit\Framework\TestCase;

final class ChecklistItemEndpointTest extends TestCase
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
    }

    public function testCreateChecklistItemResponseReturnsCreatedItem(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Coding');

        $response = build_create_checklist_item_response(
            $this->pdo,
            ['id' => 'user-1'],
            ['activity_id' => $activity['id']],
        );

        $this->assertSame(201, $response['status']);
        $this->assertTrue($response['body']['ok']);
        $this->assertSame(
            20,
            (int) $response['body']['item']['min_duration_minutes'],
        );
    }

    public function testListChecklistItemsResponseReturnsItems(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Coding');
        create_checklist_item($this->pdo, 'user-1', (int) $activity['id']);

        $response = build_list_checklist_items_response($this->pdo, [
            'id' => 'user-1',
        ]);

        $this->assertSame(200, $response['status']);
        $this->assertCount(1, $response['body']['items']);
    }

    public function testUpdateChecklistItemResponseReturnsUpdatedItem(): void
    {
        $coding = create_activity($this->pdo, 'user-1', 'Coding');
        $reading = create_activity($this->pdo, 'user-1', 'Reading');
        $item = create_checklist_item(
            $this->pdo,
            'user-1',
            (int) $coding['id'],
        );

        $response = build_update_checklist_item_response(
            $this->pdo,
            ['id' => 'user-1'],
            [
                'id' => $item['id'],
                'activity_id' => $reading['id'],
                'min_duration_minutes' => 35,
            ],
        );

        $this->assertSame(200, $response['status']);
        $this->assertSame(
            35,
            (int) $response['body']['item']['min_duration_minutes'],
        );
        $this->assertSame(
            (int) $reading['id'],
            (int) $response['body']['item']['activity_id'],
        );
    }

    public function testCreateChecklistItemResponseReturns422WhenActivityIdMissing(): void
    {
        $response = build_create_checklist_item_response(
            $this->pdo,
            ['id' => 'user-1'],
            [],
        );

        $this->assertSame(422, $response['status']);
        $this->assertFalse($response['body']['ok']);
    }
}
