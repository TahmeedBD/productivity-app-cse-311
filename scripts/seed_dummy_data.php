#!/usr/bin/env php
<?php
/**
 * CLI: Generate natural dummy productivity data for one user.
 *
 * Date range: exactly 30 calendar days ending on 5 May (inclusive).
 *
 * Constraints for the past 5 days:
 * - Awake window: 07:30 AM to 11:30 PM.
 * - First 30 mins: Always 'routine' activity ("Morning plan + habits").
 * - Timing: All activities start/stop on :00, :30, or sometimes :15.
 * - Gaps: Very few (1 or 2 per day, ~30 min or ~2 hour).
 * - Blocks: Much longer.
 *
 * Activities match personal tags: chess, code, coding, guitar, music, piano,
 * routine, singing, study, research, valorant, workout.
 *
 * Usage:
 *   php scripts/seed_dummy_data.php <email>
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/auth/helpers.php';

const SEED_DAY_COUNT = 30;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this script from the terminal (CLI only).\n");
    exit(1);
}

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/seed_dummy_data.php <email>\n");
    exit(1);
}

$email = trim((string) $argv[1]);
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Invalid email.\n");
    exit(1);
}

$host = getenv('DB_HOST') ?: 'db';
$port = (int) (getenv('DB_PORT') ?: '3306');
$dbName = getenv('DB_NAME') ?: 'productivity_app';
$dbUser = getenv('DB_USER') ?: 'app_user';
$dbPass = getenv('DB_PASSWORD') ?: 'app_password';

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $host,
    $port,
    $dbName,
);

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    // Set connection timezone to match your local UTC+6 offset
    // so that TIMESTAMP columns align with the TIME columns (wake/sleep)
    $pdo->exec("SET time_zone = '+06:00';");
} catch (PDOException $e) {
    fwrite(STDERR, 'Database connection failed: ' . $e->getMessage() . "\n");
    exit(1);
}

$pdo->beginTransaction();

try {
    $userId = ensure_user($pdo, $email);
    wipe_user_productivity_data($pdo, $userId);
    [$activityIds, $subtypeIds] = seed_activities($pdo, $userId);
    $checklistIds = seed_checklist_items(
        $pdo,
        $userId,
        $activityIds,
        $subtypeIds,
    );
    $endDate = seed_end_date();
    seed_date_range(
        $pdo,
        $userId,
        $activityIds,
        $subtypeIds,
        $checklistIds,
        $email,
        $endDate,
    );
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . "\n");
    exit(1);
}

fwrite(STDOUT, "Dummy data seeded for {$email} (user id {$userId}).\n");
exit(0);

// -----------------------------------------------------------------------------

function seed_end_date(): DateTimeImmutable
{
    $raw = getenv('SEED_END_DATE') ?: '2026-05-05';
    return new DateTimeImmutable($raw, new DateTimeZone('UTC'));
}

function ensure_user(PDO $pdo, string $email): string
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
    $stmt->execute([':e' => $email]);
    $row = $stmt->fetch();

    if ($row !== false) {
        return (string) $row['id'];
    }

    $local = strstr($email, '@', true) ?: 'user';
    $local = preg_replace('/[^a-zA-Z0-9_]/', '_', $local) ?: 'user';
    $local = substr($local, 0, 80);
    $username = unique_username($pdo, $local);
    $id = generate_uuid();
    $hash = password_hash('DummySeed#ChangeMe1', PASSWORD_BCRYPT);

    $ins = $pdo->prepare(
        'INSERT INTO users (id, email, username, password_hash) VALUES (:id, :e, :u, :h)',
    );
    $ins->execute([
        ':id' => $id,
        ':e' => $email,
        ':u' => $username,
        ':h' => $hash,
    ]);

    fwrite(STDOUT, "Created user; login password is DummySeed#ChangeMe1\n");

    return $id;
}

function unique_username(PDO $pdo, string $base): string
{
    $candidate = $base;
    $n = 0;
    while (true) {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM users WHERE username = :u LIMIT 1',
        );
        $stmt->execute([':u' => $candidate]);
        if ($stmt->fetch() === false) {
            return $candidate;
        }
        $n++;
        $candidate = $base . '_' . $n;
    }
}

function wipe_user_productivity_data(PDO $pdo, string $userId): void
{
    $pdo->prepare(
        'DELETE te FROM time_entries te INNER JOIN daily_logs dl ON dl.id = te.daily_log_id WHERE dl.user_id = :uid',
    )->execute([':uid' => $userId]);
    $pdo->prepare(
        'DELETE FROM daily_completions WHERE user_id = :uid',
    )->execute([':uid' => $userId]);
    $pdo->prepare('DELETE FROM daily_logs WHERE user_id = :uid')->execute([
        ':uid' => $userId,
    ]);
    $pdo->prepare('DELETE FROM checklist_items WHERE user_id = :uid')->execute([
        ':uid' => $userId,
    ]);
    $pdo->prepare(
        'DELETE ast FROM activity_subtypes ast INNER JOIN activities a ON a.id = ast.activity_id WHERE a.user_id = :uid',
    )->execute([':uid' => $userId]);
    $pdo->prepare('DELETE FROM activities WHERE user_id = :uid')->execute([
        ':uid' => $userId,
    ]);
}

function seed_activities(PDO $pdo, string $userId): array
{
    $tree = [
        'chess' => ['Blitz', 'Puzzles', 'Opening prep'],
        'code' => ['Features', 'Refactor', 'Bugfix'],
        'coding' => ['Deep work', 'Katas', 'Tutorials'],
        'guitar' => ['Scales', 'Song practice'],
        'music' => ['Listening', 'Theory'],
        'piano' => ['Repertoire', 'Technique'],
        'routine' => ['Daily'],
        'singing' => ['Warm-up', 'Repertoire'],
        'study' => ['Coursework', 'Exam prep'],
        'research' => ['Literature review', 'Lab notes'],
        'valorant' => ['Competitive', 'Deathmatch', 'VOD review'],
        'workout' => ['Strength', 'Cardio', 'Mobility'],
    ];

    $activityIds = [];
    $subtypeIds = [];

    $actStmt = $pdo->prepare(
        'INSERT INTO activities (user_id, name) VALUES (:uid, :name)',
    );
    $subStmt = $pdo->prepare(
        'INSERT INTO activity_subtypes (activity_id, name) VALUES (:aid, :name)',
    );

    foreach ($tree as $actName => $subtypes) {
        $actStmt->execute([':uid' => $userId, ':name' => $actName]);
        $aid = (int) $pdo->lastInsertId();
        $activityIds[$actName] = $aid;

        foreach ($subtypes as $subName) {
            $subStmt->execute([':aid' => $aid, ':name' => $subName]);
            $subtypeIds[$actName . '|' . $subName] = (int) $pdo->lastInsertId();
        }
    }

    return [$activityIds, $subtypeIds];
}

function seed_checklist_items(
    PDO $pdo,
    string $userId,
    array $activityIds,
    array $subtypeIds,
): array {
    $defs = [
        'routine' => [
            'activity' => 'routine',
            'subtype' => 'Daily',
            'min' => 15,
        ],
        'study' => [
            'activity' => 'study',
            'subtype' => 'Coursework',
            'min' => 30,
        ],
        'research' => [
            'activity' => 'research',
            'subtype' => 'Literature review',
            'min' => 25,
        ],
        'workout' => [
            'activity' => 'workout',
            'subtype' => 'Strength',
            'min' => 35,
        ],
        'chess' => ['activity' => 'chess', 'subtype' => 'Blitz', 'min' => 20],
    ];

    $hasSubtypeCol =
        (int) $pdo
            ->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'checklist_items' AND COLUMN_NAME = 'activity_subtype_id'",
            )
            ->fetchColumn() > 0;

    $stmt = $hasSubtypeCol
        ? $pdo->prepare(
            'INSERT INTO checklist_items (user_id, activity_id, activity_subtype_id, min_duration_minutes) VALUES (:uid, :aid, :sid, :min)',
        )
        : $pdo->prepare(
            'INSERT INTO checklist_items (user_id, activity_id, min_duration_minutes) VALUES (:uid, :aid, :min)',
        );

    $checklistIds = [];
    foreach ($defs as $key => $cfg) {
        $actName = $cfg['activity'];
        $subName = $cfg['subtype'];
        $aid = $activityIds[$actName];
        $params = [':uid' => $userId, ':aid' => $aid, ':min' => $cfg['min']];
        if ($hasSubtypeCol) {
            $params[':sid'] = $subtypeIds[$actName . '|' . $subName];
        }
        $stmt->execute($params);
        $checklistIds[$key] = (int) $pdo->lastInsertId();
    }

    return $checklistIds;
}

function seed_date_range(
    PDO $pdo,
    string $userId,
    array $activityIds,
    array $subtypeIds,
    array $checklistIds,
    string $email,
    DateTimeImmutable $endDate,
): void {
    $incomplete = build_habit_incomplete_map($email);
    $currentLocal = new DateTimeImmutable(
        'now',
        new DateTimeZone('Asia/Dhaka'),
    );
    $currentLocalDate = $currentLocal->format('Y-m-d');
    $currentLocalMinute =
        ((int) $currentLocal->format('H')) * 60 +
        (int) $currentLocal->format('i');

    $logStmt = $pdo->prepare(
        'INSERT INTO daily_logs (user_id, date, wake_time, sleep_time) VALUES (:uid, :d, :w, :s)',
    );
    $entryStmt = $pdo->prepare(
        'INSERT INTO time_entries (daily_log_id, activity_id, activity_subtype_id, `start`, `end`, state, notes) VALUES (:dl, :aid, :sid, :st, :en, :state, :notes)',
    );
    $dcStmt = $pdo->prepare(
        'INSERT INTO daily_completions (user_id, daily_log_id, checklist_item_id, is_completed) VALUES (:uid, :dl, :ci, :done)',
    );

    for ($i = SEED_DAY_COUNT - 1; $i >= 0; $i--) {
        $day = $endDate->modify('-' . $i . ' days');
        $dateStr = $day->format('Y-m-d');
        $isToday = $i === 0;
        $isRecent = $i < 14;
        $isActualCurrentDate = $dateStr === $currentLocalDate;

        // Awake time
        if ($isRecent) {
            $wakeMin = 7 * 60 + 30; // 07:30 AM
            $dayEndMin = 23 * 60 + 30; // 11:30 PM
        } else {
            $wakeMin = 420 + (($i * 7) % 75);
            $dayEndMin = 1320 + (($i * 11) % 105);
        }

        // Round wake/sleep times to 15 mins for consistency in recent days
        if ($isRecent) {
            $wakeMin = (int) (round($wakeMin / 15) * 15);
            $dayEndMin = (int) (round($dayEndMin / 15) * 15);
        }

        $logStmt->execute([
            ':uid' => $userId,
            ':d' => $dateStr,
            ':w' => minutes_to_time($wakeMin),
            ':s' => minutes_to_time($dayEndMin),
        ]);
        $dailyLogId = (int) $pdo->lastInsertId();

        $entrySeedEndMin = $dayEndMin;

        if ($isActualCurrentDate) {
            $entrySeedEndMin = max(
                $wakeMin + 30,
                min(
                    $dayEndMin,
                    round_down_to_quarter_hour($currentLocalMinute),
                ),
            );
        }

        if ($isRecent) {
            $slots = build_recent_day_slots(
                $dateStr,
                $wakeMin,
                $entrySeedEndMin,
                $i,
                $activityIds,
                $subtypeIds,
            );
        } else {
            $slots = build_old_day_slots(
                $dateStr,
                $wakeMin,
                $entrySeedEndMin,
                $i,
                $activityIds,
                $subtypeIds,
            );
        }

        foreach ($slots as $slot) {
            $entryStmt->execute([
                ':dl' => $dailyLogId,
                ':aid' => $slot['activity_id'],
                ':sid' => $slot['subtype_id'],
                ':st' => $slot['start'],
                ':en' => $slot['end'],
                ':state' => 'completed',
                ':notes' => $slot['notes'],
            ]);
        }

        foreach ($checklistIds as $habitKey => $cid) {
            $done = !in_array($i, $incomplete[$habitKey], true);
            $dcStmt->execute([
                ':uid' => $userId,
                ':dl' => $dailyLogId,
                ':ci' => $cid,
                ':done' => $done ? 1 : 0,
            ]);
        }
    }
}

function build_recent_day_slots(
    string $dateStr,
    int $wakeMin,
    int $dayEndMin,
    int $dayIndex,
    array $activityIds,
    array $subtypeIds,
): array {
    $slots = [];
    $cursor = $wakeMin;

    // First 30 mins: routine
    $slots[] = [
        'activity_id' => $activityIds['routine'],
        'subtype_id' => $subtypeIds['routine|Daily'],
        'start' => combine($dateStr, minutes_to_time($cursor)),
        'end' => combine($dateStr, minutes_to_time($cursor + 30)),
        'notes' => 'Morning plan + habits',
    ];
    $cursor += 30;

    // Define 1 or 2 gaps
    $gap1At = 12 * 60; // Noon
    $gap1Dur = 30;
    $gap2At = 18 * 60; // 6 PM
    $gap2Dur = 120; // 2 hours

    $pool = [
        [
            'act' => 'code',
            'sub' => 'Features',
            'note' => 'Large feature implementation',
        ],
        [
            'act' => 'coding',
            'sub' => 'Deep work',
            'note' => 'Complex problem solving',
        ],
        [
            'act' => 'study',
            'sub' => 'Coursework',
            'note' => 'Intensive study session',
        ],
        [
            'act' => 'research',
            'sub' => 'Literature review',
            'note' => 'Deep research',
        ],
        ['act' => 'workout', 'sub' => 'Strength', 'note' => 'Heavy lifting'],
        ['act' => 'chess', 'sub' => 'Opening prep', 'note' => 'Theory study'],
        ['act' => 'piano', 'sub' => 'Repertoire', 'note' => 'Bach practice'],
    ];

    // Simple deterministic shuffle
    $seed = crc32($dateStr);
    $count = count($pool);
    for ($j = 0; $j < $count; $j++) {
        $idx = ($seed + $j) % $count;
        $temp = $pool[$j];
        $pool[$j] = $pool[$idx];
        $pool[$idx] = $temp;
    }

    $pIdx = 0;
    while ($cursor < $dayEndMin) {
        // Check for gaps
        if ($cursor == $gap1At) {
            $cursor += $gap1Dur;
            continue;
        }
        if ($cursor == $gap2At) {
            $cursor += $gap2Dur;
            continue;
        }

        // Determine next block end
        $nextStop = $dayEndMin;
        if ($cursor < $gap1At) {
            $nextStop = $gap1At;
        } elseif ($cursor < $gap2At) {
            $nextStop = $gap2At;
        }

        // Ensure we don't pick a tiny duration
        if ($nextStop - $cursor < 30) {
            $cursor = $nextStop;
            continue;
        }

        // Take a long block (e.g., 2-4 hours or until next gap)
        $dur = 120 + (($seed + $cursor) % 4) * 30;
        $blockEnd = min($nextStop, $cursor + $dur);

        // Round blockEnd to 00, 30, or 15
        if ($blockEnd < $nextStop) {
            $rem = $blockEnd % 15;
            if ($rem != 0) {
                $blockEnd -= $rem;
            }
            if ($blockEnd <= $cursor) {
                $blockEnd = min($nextStop, $cursor + 30);
            }
        }

        // Final safety check: ensure block stays within wake cycle
        if ($blockEnd > $dayEndMin) {
            $blockEnd = $dayEndMin;
        }

        if ($blockEnd <= $cursor) {
            $cursor = $nextStop;
            continue;
        }

        $p = $pool[$pIdx % $count];
        $pIdx++;
        $slots[] = [
            'activity_id' => $activityIds[$p['act']],
            'subtype_id' => $subtypeIds[$p['act'] . '|' . $p['sub']],
            'start' => combine($dateStr, minutes_to_time($cursor)),
            'end' => combine($dateStr, minutes_to_time($blockEnd)),
            'notes' => $p['note'],
        ];
        $cursor = $blockEnd;
    }

    return $slots;
}

function build_old_day_slots(
    string $dateStr,
    int $wakeMin,
    int $dayEndMin,
    int $dayIndex,
    array $activityIds,
    array $subtypeIds,
): array {
    $slots = [];
    $cursor = $wakeMin + 5;
    $add = function ($dur, $act, $sub, $note) use (
        &$cursor,
        $dayEndMin,
        $activityIds,
        $subtypeIds,
        $dateStr,
        &$slots,
    ) {
        if ($cursor + $dur > $dayEndMin) {
            return;
        }
        $slots[] = [
            'activity_id' => $activityIds[$act],
            'subtype_id' => $subtypeIds[$act . '|' . $sub],
            'start' => combine($dateStr, minutes_to_time($cursor)),
            'end' => combine($dateStr, minutes_to_time($cursor + $dur)),
            'notes' => $note,
        ];
        $cursor += $dur + 10;
    };
    $add(30, 'routine', 'Daily', 'Morning');
    $add(60, 'code', 'Features', 'Work');
    $add(45, 'workout', 'Cardio', 'Gym');
    return $slots;
}

function build_habit_incomplete_map(string $email): array
{
    $targets = [
        'routine' => 1,
        'study' => 2,
        'research' => 4,
        'workout' => 8,
        'chess' => 12,
    ];
    $out = [];
    foreach ($targets as $key => $nIncomplete) {
        $out[$key] = deterministic_pick_days(
            SEED_DAY_COUNT,
            $nIncomplete,
            $email . '|' . $key,
        );
    }
    return $out;
}

function deterministic_pick_days(int $total, int $count, string $salt): array
{
    $picked = [];
    $h = 0;
    while (count($picked) < $count) {
        $idx =
            hexdec(substr(hash('sha256', $salt . ':' . $h++), 0, 8)) % $total;
        $picked[$idx] = true;
    }
    $keys = array_keys($picked);
    sort($keys);
    return $keys;
}

function round_down_to_quarter_hour(int $minutes): int
{
    return intdiv($minutes, 15) * 15;
}

function minutes_to_time(int $m): string
{
    return sprintf('%02d:%02d:00', intdiv($m, 60), $m % 60);
}
function combine(string $date, string $hms): string
{
    return $date . ' ' . $hms;
}
function pick_workout_subtype(int $s): string
{
    return ['Strength', 'Cardio', 'Mobility'][$s % 3];
}

