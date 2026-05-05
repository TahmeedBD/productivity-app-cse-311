<?php
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth/helpers.php';

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

start_session();

if (!empty($_SESSION['user_id'])) {
    header('Location: /');
    exit();
}
