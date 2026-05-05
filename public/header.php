<?php
require_once __DIR__ . '/../src/db.php';

if (!function_exists('asset_version')) {
    function asset_version(string $publicPath): string
    {
        static $cache = [];

        if (isset($cache[$publicPath])) {
            return $cache[$publicPath];
        }

        $normalizedPath = ltrim($publicPath, '/');
        $absolutePath = __DIR__ . '/' . $normalizedPath;

        $cache[$publicPath] = is_file($absolutePath)
            ? (string) filemtime($absolutePath)
            : '1';

        return $cache[$publicPath];
    }
}

if (!function_exists('asset_path')) {
    function asset_path(string $publicPath): string
    {
        return $publicPath . '?v=' . rawurlencode(asset_version($publicPath));
    }
}
?>
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
    <link rel="stylesheet" href="<?= htmlspecialchars(
        asset_path('/css/style.css'),
    ) ?>">
    <?php if (isset($pageCSS)): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars(
            asset_path('/css/' . (string) $pageCSS),
        ) ?>">
    <?php endif; ?>
</head>
<body<?= isset($bodyClass)
    ? ' class="' . htmlspecialchars($bodyClass) . '"'
    : '' ?>>
    <nav class="navbar" role="navigation" aria-label="Main navigation">
        <div class="container nav-content">
            <div class="nav-brand">
                <a href="/" class="nav-logo">
                    ⏱ Productivity<span>Tracker</span>
                </a>
            </div>
            <ul class="nav-links" role="list">
                <li><a href="/" <?= basename($_SERVER['PHP_SELF']) ===
                'index.php'
                    ? 'class="active" aria-current="page"'
                    : '' ?>>Dashboard</a></li>
                <li><a href="/reports.php" <?= basename(
                    $_SERVER['PHP_SELF'],
                ) === 'reports.php'
                    ? 'class="active" aria-current="page"'
                    : '' ?>>Overview</a></li>
                <li><a href="/activities.php" <?= basename(
                    $_SERVER['PHP_SELF'],
                ) === 'activities.php'
                    ? 'class="active" aria-current="page"'
                    : '' ?>>Activities</a></li>
                <li><a href="/time_logger.php" <?= basename(
                    $_SERVER['PHP_SELF'],
                ) === 'time_logger.php'
                    ? 'class="active" aria-current="page"'
                    : '' ?>>Time Log</a></li>
                <li><a href="/settings.php" <?= basename(
                    $_SERVER['PHP_SELF'],
                ) === 'settings.php'
                    ? 'class="active" aria-current="page"'
                    : '' ?>>Settings</a></li>
            </ul>
            <div class="nav-account">
                <?php if (isset($currentUser)): ?>
                    <span class="nav-welcome">Welcome, <?= htmlspecialchars(
                        (string) ($currentUser['username'] ?? ''),
                    ) ?>!</span>
                    <button
                        type="button"
                        id="nav-logout-button"
                        class="nav-link-button nav-account__action"
                        data-csrf-token="<?= htmlspecialchars(
                            (string) ($_SESSION['csrf_token'] ?? ''),
                        ) ?>"
                    >
                        Logout
                    </button>
                <?php else: ?>
                    <a href="/login.php" class="nav-link-button nav-account__action" <?= basename(
                        $_SERVER['PHP_SELF'],
                    ) === 'login.php'
                        ? 'aria-current="page"'
                        : '' ?>>Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <main class="container page-main" id="main-content">
