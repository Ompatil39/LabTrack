<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

use LabTrack\Repository\TelemetryRepository;
use LabTrack\Security\AgentSecurity;

$pdo = db_pdo();
$teleRepo = new TelemetryRepository();

echo "=== STEP 0: Reset drift alerts and has_hardware_drift for Workstation #1 ===\n";
$pdo->exec("DELETE FROM hardware_drift_alerts WHERE workstation_id = 1");
$pdo->exec("UPDATE workstations SET has_hardware_drift = 0 WHERE workstation_id = 1");

$cred = $teleRepo->findCredentialByWorkstationId(1);
$appKey = (string) (
    ($_ENV['AGENT_SECRET_KEY'] ?? null)
    ?: ($_ENV['APP_KEY'] ?? null)
    ?: (function_exists('app_config') ? (app_config()['app']['key'] ?? null) : null)
    ?: 'labtrack-32-byte-master-secret-key!'
);
$signingSecret = AgentSecurity::decrypt((string) $cred['signing_secret_encrypted'], $appKey);
$apiKeyId = (string) $cred['api_key_id'];

function sendSignedInventory(string $apiKeyId, string $secret, array $payload): array
{
    $body = json_encode($payload);
    $ts = time();
    $nonce = bin2hex(random_bytes(16));
    $path = '/api/agent/inventory';
    $canonical = AgentSecurity::buildCanonicalString('POST', $path, $ts, $nonce, $body);
    $signature = AgentSecurity::computeHmac($canonical, $secret);

    $ch = curl_init("http://127.0.0.1:8088{$path}");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            "X-Agent-Key-Id: {$apiKeyId}",
            "X-Agent-Timestamp: {$ts}",
            "X-Agent-Nonce: {$nonce}",
            "X-Agent-Signature: {$signature}",
        ],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'body' => (string) $res];
}

$inventoryPayload = [
    'reported_hardware' => [
        'ram_bytes' => 8 * 1024 * 1024 * 1024, // 8 GB (Registered is 16 GB -> drift)
        'disk_bytes' => 512 * 1024 * 1024 * 1024,
        'cpu_model' => 'Intel Core i7-10700 @ 2.90GHz',
        'mac_address' => '00:1A:2B:3C:4D:01',
    ],
    'installed_software' => [
        ['name' => 'Python 3.12', 'version' => '3.12.8'],
    ],
];

echo "\n=== STEP 1: Send inventory over HTTP with 8 GB RAM (First send) ===\n";
$res1 = sendSignedInventory($apiKeyId, $signingSecret, $inventoryPayload);
echo "HTTP Status: {$res1['code']}\n";
echo "Response: {$res1['body']}\n";

echo "\n--- DB Drift Alerts (After 1st send) ---\n";
$alerts1 = $pdo->query("SELECT drift_id, workstation_id, field_name, expected_value, reported_value, status, detected_at, resolved_at FROM hardware_drift_alerts WHERE workstation_id = 1")->fetchAll(PDO::FETCH_ASSOC);
print_r($alerts1);

$wsRow1 = $pdo->query("SELECT has_hardware_drift FROM workstations WHERE workstation_id = 1")->fetch(PDO::FETCH_ASSOC);
echo "workstations.has_hardware_drift: {$wsRow1['has_hardware_drift']} (Expected: 1)\n";

$driftId = (int) ($alerts1[0]['drift_id'] ?? 0);

echo "\n=== STEP 2: Send identical inventory over HTTP (Second send) ===\n";
$res2 = sendSignedInventory($apiKeyId, $signingSecret, $inventoryPayload);
echo "HTTP Status: {$res2['code']}\n";
echo "Response: {$res2['body']}\n";

echo "\n--- DB Drift Alerts (After 2nd send - assert NO duplicates) ---\n";
$alerts2 = $pdo->query("SELECT drift_id, workstation_id, field_name, expected_value, reported_value, status, detected_at, resolved_at FROM hardware_drift_alerts WHERE workstation_id = 1")->fetchAll(PDO::FETCH_ASSOC);
print_r($alerts2);
echo "Total alert rows for Workstation #1: " . count($alerts2) . " (Expected: exactly 1)\n";

echo "\n=== STEP 3: Resolve alert via POST /workstations/1/drift/{$driftId}/resolve ===\n";

// 1. GET /login to get initial session & csrf
$chLogin = curl_init("http://127.0.0.1:8088/login");
curl_setopt_array($chLogin, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
]);
$loginPage = (string) curl_exec($chLogin);
curl_close($chLogin);

preg_match('/Set-Cookie:\s*PHPSESSID=([^;]+)/i', $loginPage, $mSess);
$sessId = $mSess[1] ?? '';
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginPage, $mCsrf);
$csrfToken = $mCsrf[1] ?? '';

// 2. Perform Login as Admin
$chDoLogin = curl_init("http://127.0.0.1:8088/login");
curl_setopt_array($chDoLogin, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'username' => 'admin',
        'password' => 'admin1234',
        'csrf_token' => $csrfToken,
    ]),
    CURLOPT_HTTPHEADER => [
        "Cookie: PHPSESSID={$sessId}",
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
]);
$loginRes = (string) curl_exec($chDoLogin);
curl_close($chDoLogin);

// Check if new session id was issued upon login
if (preg_match('/Set-Cookie:\s*PHPSESSID=([^;]+)/i', $loginRes, $mNewSess)) {
    $sessId = $mNewSess[1];
}

// 3. GET /workstations/1 to get current authenticated session CSRF token
$chWs = curl_init("http://127.0.0.1:8088/workstations/1");
curl_setopt_array($chWs, [
    CURLOPT_HTTPHEADER => [
        "Cookie: PHPSESSID={$sessId}",
    ],
    CURLOPT_RETURNTRANSFER => true,
]);
$wsHtml = (string) curl_exec($chWs);
curl_close($chWs);

preg_match('/name="csrf_token"\s+value="([^"]+)"/', $wsHtml, $mWs);
$wsCsrf = $mWs[1] ?? $csrfToken;

// 4. POST resolve endpoint as Admin
$chResolve = curl_init("http://127.0.0.1:8088/workstations/1/drift/{$driftId}/resolve");
curl_setopt_array($chResolve, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'action' => 'Resolved',
        'csrf_token' => $wsCsrf,
    ]),
    CURLOPT_HTTPHEADER => [
        "Cookie: PHPSESSID={$sessId}",
        'Accept: application/json',
        'X-Requested-With: XMLHttpRequest',
    ],
    CURLOPT_RETURNTRANSFER => true,
]);
$resolveRes = curl_exec($chResolve);
$resolveCode = curl_getinfo($chResolve, CURLINFO_HTTP_CODE);
curl_close($chResolve);

echo "Resolve HTTP Code: {$resolveCode}\n";
echo "Resolve Body: {$resolveRes}\n";

echo "\n--- DB State After Resolution ---\n";
$alertsResolved = $pdo->query("SELECT drift_id, workstation_id, field_name, expected_value, reported_value, status, detected_at, resolved_at FROM hardware_drift_alerts WHERE workstation_id = 1")->fetchAll(PDO::FETCH_ASSOC);
print_r($alertsResolved);

$wsRowResolved = $pdo->query("SELECT has_hardware_drift FROM workstations WHERE workstation_id = 1")->fetch(PDO::FETCH_ASSOC);
echo "workstations.has_hardware_drift: {$wsRowResolved['has_hardware_drift']} (Expected: 0)\n";
