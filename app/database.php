<?php

declare(strict_types=1);

function app_env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);

    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

function app_db_config(): array
{
    $driver = app_env('DB_DRIVER', 'mysql');

    if ($driver === 'sqlite') {
        $databasePath = app_env('DB_DATABASE', dirname(__DIR__) . '/database/app.sqlite');

        return [
            'driver' => 'sqlite',
            'dsn' => 'sqlite:' . $databasePath,
            'username' => null,
            'password' => null,
        ];
    }

    $host = app_env('DB_HOST', '127.0.0.1');
    $port = app_env('DB_PORT', '3306');
    $database = app_env('DB_NAME', 'carce_app');
    $charset = app_env('DB_CHARSET', 'utf8mb4');

    return [
        'driver' => 'mysql',
        'dsn' => sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $database, $charset),
        'username' => app_env('DB_USER', 'root'),
        'password' => app_env('DB_PASS', ''),
    ];
}

function app_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = app_db_config();

    $pdo = new PDO(
        $config['dsn'],
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $pdo;
}