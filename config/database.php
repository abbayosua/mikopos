<?php

$pgUrl = getenv('POSTGRES_URL') ?: getenv('DATABASE_URL') ?: '';

if ($pgUrl) {
    $parts = parse_url($pgUrl);
    return [
        'host'     => $parts['host'] ?? '127.0.0.1',
        'port'     => $parts['port'] ?? '5432',
        'database' => ltrim($parts['path'] ?? 'mikopos', '/'),
        'username' => $parts['user'] ?? 'root',
        'password' => $parts['pass'] ?? '',
    ];
}

return [
    'host'     => getenv('DB_HOST') ?: getenv('POSTGRES_HOST') ?: '127.0.0.1',
    'port'     => getenv('DB_PORT') ?: getenv('POSTGRES_PORT') ?: '5432',
    'database' => getenv('DB_NAME') ?: getenv('POSTGRES_DATABASE') ?: 'mikopos',
    'username' => getenv('DB_USER') ?: getenv('POSTGRES_USER') ?: 'root',
    'password' => getenv('DB_PASS') ?: getenv('POSTGRES_PASSWORD') ?: '',
];
