<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

$pdo = db_pdo();

// 1. Log in as admin to call /api/workstations/5/telemetry
$ch = curl_init("http://127.0.0.1:8088/login");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
]);
$res1 = (string) curl_exec($ch);
curl_close($ch);

preg_match('/Set-Cookie:\s*PHPSESSID=([^;]+)/i', $res1, $mSess);
$sessId = $mSess[1] ?? '';
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $res1, $mCsrf);
$csrf = $mCsrf[1] ?? '';

$ch2 = curl_init("http://127.0.0.1:8088/login");
curl_setopt_array($ch2, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'username' => 'admin',
        'password' => 'admin1234',
        'csrf_token' => $csrf,
    ]),
    CURLOPT_HTTPHEADER => ["Cookie: PHPSESSID={$sessId}"],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
]);
$res2 = (string) curl_exec($ch2);
curl_close($ch2);

if (preg_match('/Set-Cookie:\s*PHPSESSID=([^;]+)/i', $res2, $mNew)) {
    $sessId = $mNew[1];
}

function getWsTelemetry(int $wsId, string $sessId): array
{
    $ch = curl_init("http://127.0.0.1:8088/api/workstations/{$wsId}/telemetry");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            "Cookie: PHPSESSID={$sessId}",
            'Accept: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $res = (string) curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'data' => json_decode($res, true)];
}

echo "=== STEP 1: Set last_seen_at = NOW() (Active Agent) ===\n";
$pdo->exec("UPDATE workstations SET last_seen_at = NOW() WHERE workstation_id = 5");
$resOnline = getWsTelemetry(5, $sessId);
echo "HTTP Code: {$resOnline['code']}\n";
echo "Workstation #5 Status: " . ($resOnline['data']['data']['status'] ?? 'UNKNOWN') . " (Expected: Online)\n";
echo "Last Seen: " . ($resOnline['data']['data']['last_seen_at'] ?? 'null') . "\n";

echo "\n=== STEP 2: Stop agent for 2 heartbeat intervals (>120s, e.g. 150 seconds ago) ===\n";
$pdo->exec("UPDATE workstations SET last_seen_at = DATE_SUB(NOW(), INTERVAL 150 SECOND) WHERE workstation_id = 5");
$resOffline = getWsTelemetry(5, $sessId);
echo "HTTP Code: {$resOffline['code']}\n";
echo "Workstation #5 Status: " . ($resOffline['data']['data']['status'] ?? 'UNKNOWN') . " (Expected: Offline)\n";
echo "Last Seen: " . ($resOffline['data']['data']['last_seen_at'] ?? 'null') . "\n";
