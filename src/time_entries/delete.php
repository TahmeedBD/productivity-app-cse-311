<?php
declare(strict_types=1);

require_once __DIR__ . '/service.php';

function delete_time_entry(\PDO $pdo, string $userId, int $entryId): void
{
    $entry = find_time_entry_for_user($pdo, $entryId, $userId);

    if ($entry === null) {
        throw new \InvalidArgumentException('Time entry not found.');
    }

    $statement = $pdo->prepare('DELETE FROM time_entries WHERE id = :id');
    $statement->execute([':id' => $entryId]);
}

function build_delete_time_entry_response(
    \PDO $pdo,
    array $currentUser,
    array $payload,
): array {
    $userId = (string) ($currentUser['id'] ?? '');
    $entryId = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    if ($userId === '') {
        throw new \InvalidArgumentException('Current user id is required.');
    }

    if ($entryId === false) {
        return [
            'status' => 422,
            'body' => ['ok' => false, 'error' => 'id is required.'],
        ];
    }

    try {
        delete_time_entry($pdo, $userId, $entryId);
    } catch (\InvalidArgumentException $exception) {
        return [
            'status' => 422,
            'body' => ['ok' => false, 'error' => $exception->getMessage()],
        ];
    }

    return [
        'status' => 200,
        'body' => ['ok' => true],
    ];
}
