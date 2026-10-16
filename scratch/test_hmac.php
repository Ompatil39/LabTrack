<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

$method = 'POST';
$path = '/api/agent/heartbeat';
$timestamp = '1700000000';
$nonce = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';
$body = '{"cpu_pct":25.5,"ram_pct":60.0,"disk_pct":45.2}';
$secret = 'my-test-signing-secret-key-32chars!!';

$bodyHash = hash('sha256', $body);
$canonical = "{$method}\n{$path}\n{$timestamp}\n{$nonce}\n{$bodyHash}";
$signature = \LabTrack\Security\AgentSecurity::computeHmac($canonical, $secret);

echo "--- PHP HMAC VECTOR ---\n";
echo "Method:    {$method}\n";
echo "Path:      {$path}\n";
echo "Timestamp: {$timestamp}\n";
echo "Nonce:     {$nonce}\n";
echo "Body:      {$body}\n";
echo "Body SHA:  {$bodyHash}\n";
echo "Secret:    {$secret}\n";
echo "Canonical:\n{$canonical}\n";
echo "Signature: {$signature}\n";
