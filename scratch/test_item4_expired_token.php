<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

$pdo = db_pdo();

$token = 'expired_test_token_' . bin2hex(random_bytes(16));
$tokenHash = hash('sha256', $token);

// Insert expired token (expired 2 hours ago)
$pdo->exec("DELETE FROM agent_credentials WHERE workstation_id = 6");
$stmt = $pdo->prepare("INSERT INTO agent_credentials (workstation_id, enrollment_token_hash, enrollment_expires_at, is_revoked)
    VALUES (6, :hash, DATE_SUB(NOW(), INTERVAL 2 HOUR), 0)");
$stmt->execute([':hash' => $tokenHash]);

$enrollPayload = json_encode([
    'token' => $token,
    'hostname' => 'EXPIRED-TEST-WS',
    'mac_address' => '00:1A:2B:3C:4D:EE',
    'ip_address' => '192.168.1.188',
]);

$ch = curl_init("http://127.0.0.1:8088/api/agent/enroll");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $enrollPayload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_RETURNTRANSFER => true,
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Expired Token HTTP Status: {$code} (Expected: 401)\n";
echo "Response Body: {$res}\n";
