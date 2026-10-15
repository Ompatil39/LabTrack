<?php

declare(strict_types=1);

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

$projectRoot = dirname(__DIR__);
$autoloadPath = $projectRoot . '/vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    throw new RuntimeException('Composer dependencies are missing. Run composer install.');
}

require_once $autoloadPath;

if (class_exists(\Dotenv\Dotenv::class)) {
    $dotenv = \Dotenv\Dotenv::createImmutable($projectRoot);
    $dotenv->safeLoad();
}

if (!function_exists('env_bool')) {
    function env_bool(string $value, bool $default = false): bool
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => $default,
        };
    }
}

if (!function_exists('app_bootstrap_logger')) {
    function app_bootstrap_logger(): object
    {
        static $logger = null;

        if (is_object($logger)) {
            return $logger;
        }

        $logDirectory = dirname(__DIR__) . '/logs';
        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0775, true);
        }

        if (class_exists(Logger::class) && class_exists(StreamHandler::class)) {
            $logger = new Logger('labtrack');
            $handler = new StreamHandler($logDirectory . '/app.log', Level::Debug);
            if (class_exists(JsonFormatter::class)) {
                $handler->setFormatter(new JsonFormatter());
            }
            $logger->pushHandler($handler);
        } else {
            $logger = new class ($logDirectory . '/app.log') {
                public function __construct(private string $logFile)
                {
                }

                public function error(string $message, array $context = []): void
                {
                    $record = json_encode([
                        'level' => 'error',
                        'message' => $message,
                        'context' => $context,
                        'timestamp' => gmdate('c'),
                    ]);
                    error_log(($record ?: $message) . PHP_EOL, 3, $this->logFile);
                }
            };
        }

        return $logger;
    }
}

if (!function_exists('app_logger')) {
    function app_logger(): object
    {
        return app_bootstrap_logger();
    }
}

$debug = env_bool((string) ($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: 'false'));
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

set_error_handler(static function (int $severity, string $message, string $file, int $line) use ($debug): bool {
    app_logger()->error('php_error', [
        'severity' => $severity,
        'message' => $message,
        'file' => $file,
        'line' => $line,
    ]);

    return !$debug;
});

set_exception_handler(static function (Throwable $exception): void {
    app_logger()->error('uncaught_exception', [
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString(),
    ]);

    http_response_code(500);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }

    echo json_encode([
        'success' => false,
        'timestamp' => gmdate('c'),
        'data' => null,
        'error' => [
            'message' => 'Internal server error.',
        ],
    ]);
});

if (!headers_sent()) {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}
