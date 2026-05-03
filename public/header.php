<?php require_once __DIR__ . '/../src/db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Personal productivity and time tracking system — log daily routines, time entries, and habits.">
    <title><?= isset($pageTitle)
        ? htmlspecialchars($pageTitle) . ' — Productivity Tracker'
        : 'Productivity Tracker' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="/css/style.css">
    <?php if (isset($pageCSS)): ?>
        <link rel="stylesheet" href="/css/<?= htmlspecialchars($pageCSS) ?>">
    <?php endif; ?>
</head>
<body>
    <nav class="navbar" role="navigation" aria-label="Main navigation">
        <div class="container nav-content">
            <a href="/" class="nav-logo">
                ⏱ Productivity<span>Tracker</span>
            </a>
            <ul class="nav-links" role="list">
                <li><a href="/" <?= basename($_SERVER['PHP_SELF']) ===
                'index.php'
                    ? 'class="active" aria-current="page"'
                    : '' ?>>Dashboard</a></li>
                <li><a href="/activities.php" <?= basename(
                    $_SERVER['PHP_SELF'],
                ) === 'activities.php'
                    ? 'class="active" aria-current="page"'
                    : '' ?>>Activities</a></li>
                <li><a href="/routines.php" <?= basename(
                    $_SERVER['PHP_SELF'],
                ) === 'routines.php'
                    ? 'class="active" aria-current="page"'
                    : '' ?>>Routines</a></li>
                <li><a href="/time_logger.php" <?= basename(
                    $_SERVER['PHP_SELF'],
                ) === 'time_logger.php'
                    ? 'class="active" aria-current="page"'
                    : '' ?>>Time Log</a></li>
                <li><a href="/reports.php" <?= basename(
                    $_SERVER['PHP_SELF'],
                ) === 'reports.php'
                    ? 'class="active" aria-current="page"'
                    : '' ?>>Reports</a></li>
            </ul>
        </div>
    </nav>
    <main class="container page-main" id="main-content">
