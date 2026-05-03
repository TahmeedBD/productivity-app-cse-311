<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/auth/helpers.php';
require_once __DIR__ . '/../src/auth/dev_login.php';
use PHPUnit\Framework\TestCase;

final class DevAutoLoginTest extends TestCase
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
            'CREATE TABLE users (
                id TEXT PRIMARY KEY,
                email TEXT NOT NULL UNIQUE,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL
            )',
        );
    }

    public function testFindOrCreateDevUserCreatesMissingUser(): void
    {
        $devUser = find_or_create_dev_user(
            $this->pdo,
            'dev@example.com',
            'Dev User',
        );

        $this->assertSame('dev@example.com', $devUser['email']);
        $this->assertSame('Dev User', $devUser['username']);
        $this->assertSame(1, $this->countUsers());
    }

    public function testFindOrCreateDevUserReturnsExistingUserWithoutDuplicate(): void
    {
        $firstUser = find_or_create_dev_user(
            $this->pdo,
            'dev@example.com',
            'Dev User',
        );
        $secondUser = find_or_create_dev_user(
            $this->pdo,
            'dev@example.com',
            'Dev User',
        );

        $this->assertSame($firstUser['id'], $secondUser['id']);
        $this->assertSame(1, $this->countUsers());
    }

    private function countUsers(): int
    {
        return (int) $this->pdo
            ->query('SELECT COUNT(*) FROM users')
            ->fetchColumn();
    }
}
