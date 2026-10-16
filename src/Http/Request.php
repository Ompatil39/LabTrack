<?php

declare(strict_types=1);

namespace LabTrack\Http;

class Request
{
    private array $server;
    private array $get;
    private array $post;
    private array $files;
    private array $cookies;
    private array $routeParams = [];
    private ?string $rawBody = null;

    public function __construct(
        ?array $get = null,
        ?array $post = null,
        ?array $server = null,
        ?array $cookies = null,
        ?array $files = null
    ) {
        $this->get = $get ?? $_GET;
        $this->post = $post ?? $_POST;
        $this->server = $server ?? $_SERVER;
        $this->cookies = $cookies ?? $_COOKIE;
        $this->files = $files ?? $_FILES;
    }

    public static function createFromGlobals(): self
    {
        return new self();
    }

    public function getMethod(): string
    {
        $method = strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'POST') {
            $override = $this->post['_method'] ?? $this->header('X-HTTP-METHOD-OVERRIDE');
            if (is_string($override) && in_array(strtoupper($override), ['PUT', 'PATCH', 'DELETE'], true)) {
                return strtoupper($override);
            }
        }
        return $method;
    }

    public function isMethod(string $method): bool
    {
        return $this->getMethod() === strtoupper($method);
    }

    public function isPost(): bool
    {
        return $this->getMethod() === 'POST';
    }

    public function isGet(): bool
    {
        return $this->getMethod() === 'GET';
    }

    public function getPath(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        if ($path === false || $path === null || $path === '') {
            $path = '/';
        }

        // Normalize base directory if app is served from a subfolder
        $scriptName = (string) ($this->server['SCRIPT_NAME'] ?? '');
        $baseDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        if ($baseDir !== '' && $baseDir !== '/' && str_starts_with($path, $baseDir)) {
            $path = substr($path, strlen($baseDir));
        }

        $trimmed = trim($path, '/');
        return $trimmed === '' ? '/' : '/' . $trimmed;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->get;
        }
        return $this->get[$key] ?? $default;
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->post;
        }
        return $this->post[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }
        if (array_key_exists($key, $this->routeParams)) {
            return $this->routeParams[$key];
        }
        if (array_key_exists($key, $this->get)) {
            return $this->get[$key];
        }
        return $default;
    }

    public function all(): array
    {
        return array_merge($this->get, $this->post, $this->routeParams);
    }

    public function header(string $name): ?string
    {
        $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($this->server[$normalized])) {
            return (string) $this->server[$normalized];
        }
        if (isset($this->server[strtoupper(str_replace('-', '_', $name))])) {
            return (string) $this->server[strtoupper(str_replace('-', '_', $name))];
        }
        return null;
    }

    public function validateCsrf(): bool
    {
        $token = $this->post['csrf_token']
            ?? $this->post['_csrf_token']
            ?? $this->header('X-CSRF-TOKEN')
            ?? $this->header('X-CSRF-Token');

        if (function_exists('verifyCsrfToken')) {
            return is_string($token) && verifyCsrfToken($token);
        }

        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['csrf_token'])) {
            return is_string($token) && hash_equals((string) $_SESSION['csrf_token'], $token);
        }

        return false;
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function getClientIp(): string
    {
        return (string) ($this->server['HTTP_X_FORWARDED_FOR'] ?? $this->server['REMOTE_ADDR'] ?? '127.0.0.1');
    }

    public function isAjax(): bool
    {
        return strtolower((string) ($this->header('X-Requested-With') ?? '')) === 'xmlhttprequest';
    }

    public function files(): array
    {
        return $this->files;
    }

    public function cookies(): array
    {
        return $this->cookies;
    }

    public function getRawBody(): string
    {
        if ($this->rawBody === null) {
            $this->rawBody = (string) file_get_contents('php://input');
        }
        return $this->rawBody;
    }

    public function wantsJson(): bool
    {
        return $this->query('format') === 'json'
            || str_contains(strtolower((string) $this->header('Accept')), 'application/json');
    }
}
