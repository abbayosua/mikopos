<?php
// PHP built-in server router script
// Usage: php -S localhost:8000 router.php

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// Serve static files from public/
if (preg_match('#^/assets/#', $path)) {
    $file = __DIR__ . '/public' . $path;
    if (file_exists($file)) {
        $mimeTypes = [
            'css' => 'text/css',
            'js'  => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'woff2' => 'font/woff2',
        ];
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if (isset($mimeTypes[$ext])) {
            header('Content-Type: ' . $mimeTypes[$ext]);
        }
        readfile($file);
        return true;
    }
    return false;
}

// Route everything else to index.php
require __DIR__ . '/index.php';
