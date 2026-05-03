<?php
declare(strict_types=1);

function default_daily_log_times(): array
{
    return [
        'wake_time' => '08:00:00',
        'sleep_time' => '23:00:00',
    ];
}

function time_to_seconds(string $time): ?int
{
    $parts = explode(':', $time);

    if (count($parts) !== 3) {
        return null;
    }

    [$hours, $minutes, $seconds] = $parts;

    if (
        !ctype_digit($hours) ||
        !ctype_digit($minutes) ||
        !ctype_digit($seconds)
    ) {
        return null;
    }

    $hourValue = (int) $hours;
    $minuteValue = (int) $minutes;
    $secondValue = (int) $seconds;

    if (
        $hourValue < 0 ||
        $hourValue > 23 ||
        $minuteValue < 0 ||
        $minuteValue > 59 ||
        $secondValue < 0 ||
        $secondValue > 59
    ) {
        return null;
    }

    return $hourValue * 3600 + $minuteValue * 60 + $secondValue;
}

function is_entry_within_awake_window(
    string $startTime,
    string $endTime,
    string $wakeTime,
    string $sleepTime,
): bool {
    $startSeconds = time_to_seconds($startTime);
    $endSeconds = time_to_seconds($endTime);
    $wakeSeconds = time_to_seconds($wakeTime);
    $sleepSeconds = time_to_seconds($sleepTime);

    if (
        $startSeconds === null ||
        $endSeconds === null ||
        $wakeSeconds === null ||
        $sleepSeconds === null
    ) {
        return false;
    }

    if ($endSeconds <= $startSeconds || $sleepSeconds <= $wakeSeconds) {
        return false;
    }

    return $startSeconds >= $wakeSeconds && $endSeconds <= $sleepSeconds;
}

function time_ranges_overlap(
    string $firstStart,
    string $firstEnd,
    string $secondStart,
    string $secondEnd,
): bool {
    $firstStartSeconds = time_to_seconds($firstStart);
    $firstEndSeconds = time_to_seconds($firstEnd);
    $secondStartSeconds = time_to_seconds($secondStart);
    $secondEndSeconds = time_to_seconds($secondEnd);

    if (
        $firstStartSeconds === null ||
        $firstEndSeconds === null ||
        $secondStartSeconds === null ||
        $secondEndSeconds === null
    ) {
        return false;
    }

    if (
        $firstEndSeconds <= $firstStartSeconds ||
        $secondEndSeconds <= $secondStartSeconds
    ) {
        return false;
    }

    return $firstStartSeconds < $secondEndSeconds &&
        $firstEndSeconds > $secondStartSeconds;
}

function has_overlapping_time_entry(
    array $candidateEntry,
    array $existingEntries,
): bool {
    $candidateStart = (string) ($candidateEntry['start'] ?? '');
    $candidateEnd = (string) ($candidateEntry['end'] ?? '');

    foreach ($existingEntries as $existingEntry) {
        $existingStart = (string) ($existingEntry['start'] ?? '');
        $existingEnd = (string) ($existingEntry['end'] ?? '');

        if (
            time_ranges_overlap(
                $candidateStart,
                $candidateEnd,
                $existingStart,
                $existingEnd,
            )
        ) {
            return true;
        }
    }

    return false;
}
