<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/activities/service.php';
require_once __DIR__ . '/../src/checklist_items/service.php';

use PHPUnit\Framework\TestCase;

final class ChecklistItemServiceTest extends TestCase
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

    public function testCreatesChecklistItemWithDefaultMinimumDuration(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Coding');

        $item = create_checklist_item(
            $this->pdo,
            'user-1',
            (int) $activity['id'],
        );

        $this->assertSame('user-1', $item['user_id']);
        $this->assertSame((int) $activity['id'], (int) $item['activity_id']);
        $this->assertSame(20, (int) $item['min_duration_minutes']);
    }

    public function testRejectsDuplicateChecklistItemForSameActivity(): void
    {
        $activity = create_activity($this->pdo, 'user-1', 'Coding');
        create_checklist_item($this->pdo, 'user-1', (int) $activity['id']);

        $this->expectException(\InvalidArgumentException::class);

        create_checklist_item($this->pdo, 'user-1', (int) $activity['id']);
    }

    public function testListsChecklistItemsWithActivityNames(): void
    {
        $reading = create_activity($this->pdo, 'user-1', 'Reading');
        $coding = create_activity($this->pdo, 'user-1', 'Coding');

        create_checklist_item($this->pdo, 'user-1', (int) $reading['id'], 30);
        create_checklist_item($this->pdo, 'user-1', (int) $coding['id'], 20);

        $items = list_checklist_items($this->pdo, 'user-1');

        $this->assertCount(2, $items);
        $this->assertSame('Coding', $items[0]['activity_name']);
        $this->assertSame('Reading', $items[1]['activity_name']);
        $this->assertSame(20, (int) $items[0]['min_duration_minutes']);
    }

    public function testUpdatesChecklistItemMinimumDuration(): void
    {
        $coding = create_activity($this->pdo, 'user-1', 'Coding');
        $reading = create_activity($this->pdo, 'user-1', 'Reading');
        $item = create_checklist_item(
            $this->pdo,
            'user-1',
            (int) $coding['id'],
        );

        $updated = update_checklist_item(
            $this->pdo,
            (int) $item['id'],
            'user-1',
            (int) $reading['id'],
            45,
        );

        $this->assertSame((int) $reading['id'], (int) $updated['activity_id']);
        $this->assertSame(45, (int) $updated['min_duration_minutes']);
    }

    public function testUpdateRejectsDuplicateActivityAssignment(): void
    {
        $coding = create_activity($this->pdo, 'user-1', 'Coding');
        $reading = create_activity($this->pdo, 'user-1', 'Reading');
        $first = create_checklist_item(
            $this->pdo,
            'user-1',
            (int) $coding['id'],
        );
        create_checklist_item($this->pdo, 'user-1', (int) $reading['id']);

        $this->expectException(\InvalidArgumentException::class);

        update_checklist_item(
            $this->pdo,
            (int) $first['id'],
            'user-1',
            (int) $reading['id'],
            20,
        );
    }
}
