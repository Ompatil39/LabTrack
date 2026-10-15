<?php

declare(strict_types=1);

if (!function_exists('config_env')) {
    function config_env(string $key, mixed $default = null): mixed
    {
        $envValue = $_ENV[$key] ?? getenv($key);

        if ($envValue === false || $envValue === null || $envValue === '') {
            return $default;
        }

        return $envValue;
    }
}

if (!function_exists('app_config')) {
    function app_config(): array
    {
        static $config = null;

        if (is_array($config)) {
            return $config;
        }

        $config = [
            'app' => [
                'env' => (string) config_env('APP_ENV', 'production'),
                'debug' => filter_var(config_env('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
                'url' => (string) config_env('APP_URL', 'http://localhost'),
            ],
            'db' => [
                'host' => (string) config_env('DB_HOST', '127.0.0.1'),
                'port' => (int) config_env('DB_PORT', 3306),
                'database' => (string) config_env('DB_DATABASE', 'labtrack'),
                'username' => (string) config_env('DB_USERNAME', 'root'),
                'password' => (string) config_env('DB_PASSWORD', ''),
            ],
            'jwt' => [
                'secret' => (string) config_env('JWT_SECRET', ''),
            ],
            'agent' => [
                'ingest_key' => (string) config_env('AGENT_INGEST_KEY', ''),
            ],
        ];

        return $config;
    }
}

if (!function_exists('db_pdo')) {
    function db_pdo(): PDO
    {
        static $pdo = null;

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $dbConfig = app_config()['db'];
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $dbConfig['host'],
            $dbConfig['port'],
            $dbConfig['database']
        );

        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $pdo;
    }
}
