<?php

declare(strict_types=1);

namespace LabTrack\Security;

use RuntimeException;

class AgentSecurity
{
    private string $encryptionKey;

    public function __construct(?string $encryptionKey = null)
    {
        if ($encryptionKey !== null && $encryptionKey !== '') {
            $raw = $encryptionKey;
        } elseif (function_exists('app_config')) {
            $raw = (string) (app_config()['app']['key'] ?? 'labtrack-32-byte-default-master-key!');
        } else {
            $raw = 'labtrack-32-byte-default-master-key!';
        }

        if (str_starts_with($raw, 'base64:')) {
            $decoded = base64_decode(substr($raw, 7), true);
            $this->encryptionKey = ($decoded !== false && strlen($decoded) === 32)
                ? $decoded
                : hash('sha256', $raw, true);
        } else {
            $this->encryptionKey = hash('sha256', $raw, true);
        }
    }

    /**
     * Encrypt a string (e.g. signing secret) using AES-256-GCM.
     * Returns base64 string formatted as: IV (12 bytes) + Tag (16 bytes) + Ciphertext.
     */
    public function encryptString(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false) {
            throw new RuntimeException('AES-256-GCM encryption failed');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt an AES-256-GCM base64 string.
     */
    public function decryptString(string $payload): ?string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 28) {
            return null;
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);

        $decrypted = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $decrypted !== false ? $decrypted : null;
    }

    /**
     * Static helper for AES-256-GCM encryption.
     */
    public static function encrypt(string $plaintext, ?string $key = null): string
    {
        return (new self($key))->encryptString($plaintext);
    }

    /**
     * Static helper for AES-256-GCM decryption.
     */
    public static function decrypt(string $payload, ?string $key = null): ?string
    {
        return (new self($key))->decryptString($payload);
    }

    /**
     * Compute raw HMAC-SHA256.
     */
    public static function computeHmac(string $canonical, string $key): string
    {
        return hash_hmac('sha256', $canonical, $key);
    }

    /**
     * Constant time HMAC verification.
     */
    public static function verifyHmac(string $expected, string $actual): bool
    {
        return hash_equals($expected, $actual);
    }

    /**
     * Canonical string for HMAC:
     * METHOD + "\n" + PATH + "\n" + TIMESTAMP + "\n" + NONCE + "\n" + hex(sha256(raw_body))
     */
    public static function buildCanonicalString(
        string $method,
        string $path,
        string|int $timestamp,
        string $nonce,
        string $rawBody
    ): string {
        $bodyHash = hash('sha256', $rawBody);
        return strtoupper(trim($method)) . "\n"
            . trim($path) . "\n"
            . (string) $timestamp . "\n"
            . trim($nonce) . "\n"
            . $bodyHash;
    }

    /**
     * Calculate HMAC-SHA256 signature.
     */
    public static function computeSignature(
        string $method,
        string $path,
        string|int $timestamp,
        string $nonce,
        string $rawBody,
        string $signingSecret
    ): string {
        $canonical = self::buildCanonicalString($method, $path, $timestamp, $nonce, $rawBody);
        return hash_hmac('sha256', $canonical, $signingSecret);
    }

    /**
     * Constant-time verification of signature.
     */
    public static function verifySignature(
        string $receivedSignature,
        string $method,
        string $path,
        string|int $timestamp,
        string $nonce,
        string $rawBody,
        string $signingSecret
    ): bool {
        $expected = self::computeSignature($method, $path, $timestamp, $nonce, $rawBody, $signingSecret);
        return hash_equals($expected, $receivedSignature);
    }

    /**
     * Generate standard UUID v4 string.
     */
    public static function generateUuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
