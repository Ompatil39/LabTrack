<?php

declare(strict_types=1);

namespace LabTrack;

final class ApiResponse
{
    public static function send(int $statusCode, bool $success, mixed $data = null, ?array $error = null): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');

        echo json_encode([
            'success' => $success,
            'timestamp' => gmdate('c'),
            'data' => $data,
            'error' => $error,
        ], JSON_UNESCAPED_SLASHES);
    }

    public static function success(mixed $data = null, int $statusCode = 200): void
    {
        self::send($statusCode, true, $data, null);
    }

    public static function error(string $message, int $statusCode, ?array $details = null): void
    {
        self::send($statusCode, false, null, [
            'message' => $message,
            'details' => $details,
        ]);
    }
}
