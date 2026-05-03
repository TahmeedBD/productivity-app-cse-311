<?php
declare(strict_types=1);

require_once __DIR__ . '/service.php';

function build_start_time_entry_response(
    \PDO $pdo,
    array $currentUser,
    array $payload,
    ?string $date = null,
    ?string $currentTime = null,
): array {
    $userId = (string) ($currentUser['id'] ?? '');

    if ($userId === '') {
        throw new \InvalidArgumentException('Current user id is required.');
    }

    $startTime = trim((string) ($payload['start'] ?? ''));
    $notes = (string) ($payload['notes'] ?? '');

    // TODO: Handle case where client is in a different timezone than the server.

    if ($startTime === '') {
        $startTime = $currentTime ?? date('H:i:s');
    }

    try {
        $entry = start_time_entry(
            $pdo,
            $userId,
            $date ?? date('Y-m-d'),
            $startTime,
            $notes,
        );

        return [
            'status' => 201,
            'body' => [
                'ok' => true,
                'entry' => $entry,
            ],
        ];
    } catch (\InvalidArgumentException $exception) {
        return [
            'status' => 400,
            'body' => [
                'ok' => false,
                'error' => $exception->getMessage(),
            ],
        ];
    }
}
