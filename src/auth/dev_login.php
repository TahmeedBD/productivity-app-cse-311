<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function is_dev_auto_login_enabled(): bool
{
    return getenv('DEV_AUTO_LOGIN_ENABLED') === '1';
}

function resolve_dev_auto_login_identity(): array
{
    return [
        'email' => getenv('DEV_AUTO_LOGIN_EMAIL') ?: 'dev@example.com',
        'username' => getenv('DEV_AUTO_LOGIN_USERNAME') ?: 'Dev User',
    ];
}

function find_or_create_dev_user(
    \PDO $pdo,
    string $email,
    string $username,
): array {
    $statement = $pdo->prepare(
        'SELECT id, email, username FROM users WHERE email = :email LIMIT 1',
    );
    $statement->execute([':email' => $email]);
    $existingUser = $statement->fetch(\PDO::FETCH_ASSOC);

    if ($existingUser !== false) {
        return $existingUser;
    }

    $insert = $pdo->prepare(
        'INSERT INTO users (id, email, username, password_hash)
         VALUES (:id, :email, :username, :password_hash)',
    );
    $insert->execute([
        ':id' => generate_uuid(),
        ':email' => $email,
        ':username' => $username,
        ':password_hash' => password_hash(
            bin2hex(random_bytes(16)),
            PASSWORD_BCRYPT,
        ),
    ]);

    $statement->execute([':email' => $email]);
    $createdUser = $statement->fetch(\PDO::FETCH_ASSOC);

    if ($createdUser === false) {
        throw new \RuntimeException(
            'Failed to create the dev auto-login user.',
        );
    }

    return $createdUser;
}

function apply_dev_auto_login(\PDO $pdo): void
{
    if (!is_dev_auto_login_enabled()) {
        return;
    }

    if (!empty($_SESSION['user_id'])) {
        return;
    }

    $identity = resolve_dev_auto_login_identity();
    $devUser = find_or_create_dev_user(
        $pdo,
        $identity['email'],
        $identity['username'],
    );

    session_regenerate_id(true);
    $_SESSION['user_id'] = $devUser['id'];
    $_SESSION['email'] = $devUser['email'];
    $_SESSION['username'] = $devUser['username'];
}
