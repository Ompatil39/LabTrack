<?php

declare(strict_types=1);

namespace LabTrack\Support;

class SimpleCache
{
    private string $cacheDir;
    private bool $enabled;

    public function __construct(?string $cacheDir = null, ?bool $enabled = null)
    {
        $this->cacheDir = $cacheDir ?? dirname(__DIR__, 2) . '/logs/cache';
        $this->enabled = $enabled ?? (getenv('APP_ENV') !== 'testing');
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0775, true);
        }
    }

    private function getFilePath(string $key): string
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $key);
        return $this->cacheDir . '/' . md5((string) $safeKey) . '.cache';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->enabled) {
            return $default;
        }

        $file = $this->getFilePath($key);
        if (!file_exists($file)) {
            return $default;
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            return $default;
        }

        $payload = @unserialize($content);
        if (!is_array($payload) || !isset($payload['expires_at'])) {
            @unlink($file);
            return $default;
        }

        if (time() >= (int) $payload['expires_at']) {
            @unlink($file);
            return $default;
        }

        return $payload['data'] ?? $default;
    }

    public function set(string $key, mixed $value, int $ttl = 60): bool
    {
        if (!$this->enabled) {
            return true;
        }

        $file = $this->getFilePath($key);
        $payload = [
            'expires_at' => time() + max(1, $ttl),
            'data' => $value,
        ];

        return (bool) @file_put_contents($file, serialize($payload), LOCK_EX);
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            return @unlink($file);
        }
        return true;
    }

    public function clear(): bool
    {
        if (!is_dir($this->cacheDir)) {
            return true;
        }

        $files = glob($this->cacheDir . '/*.cache') ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        return true;
    }

    /**
     * Retrieve an item from the cache, or execute the given Closure and store the result.
     *
     * @param callable(): mixed $callback
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        if (!$this->enabled) {
            return $callback();
        }

        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }
}
