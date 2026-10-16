<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

function loginUser(string $username, string $password): array
{
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
            'username' => $username,
            'password' => $password,
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

    // Get fresh CSRF token from workstation page
    $ch3 = curl_init("http://127.0.0.1:8088/workstations/1");
    curl_setopt_array($ch3, [
        CURLOPT_HTTPHEADER => ["Cookie: PHPSESSID={$sessId}"],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $wsHtml = (string) curl_exec($ch3);
    curl_close($ch3);

    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $wsHtml, $mWsCsrf);
    $finalCsrf = $mWsCsrf[1] ?? $csrf;

    return ['sessId' => $sessId, 'csrf' => $finalCsrf];
}

echo "=== ITEM 3.1: POST /workstations/1/enroll-token as Admin (with CSRF) ===\n";
$adminAuth = loginUser('admin', 'admin1234');
$chAdmin = curl_init("http://127.0.0.1:8088/workstations/1/enroll-token");
curl_setopt_array($chAdmin, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['csrf_token' => $adminAuth['csrf']]),
    CURLOPT_HTTPHEADER => [
        "Cookie: PHPSESSID={$adminAuth['sessId']}",
        'Accept: application/json',
        'X-Requested-With: XMLHttpRequest',
    ],
    CURLOPT_RETURNTRANSFER => true,
]);
$adminRes = curl_exec($chAdmin);
$adminCode = curl_getinfo($chAdmin, CURLINFO_HTTP_CODE);
curl_close($chAdmin);

echo "Admin Status Code: {$adminCode} (Expected: 200 or 302)\n";
echo "Admin Response: {$adminRes}\n";

echo "\n=== ITEM 3.2: POST /workstations/1/enroll-token as Incharge of that lab (prof_sharma) ===\n";
$inchargeAuth = loginUser('prof_sharma', 'admin1234');
$chIncharge = curl_init("http://127.0.0.1:8088/workstations/1/enroll-token");
curl_setopt_array($chIncharge, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['csrf_token' => $inchargeAuth['csrf']]),
    CURLOPT_HTTPHEADER => [
        "Cookie: PHPSESSID={$inchargeAuth['sessId']}",
        'Accept: application/json',
        'X-Requested-With: XMLHttpRequest',
    ],
    CURLOPT_RETURNTRANSFER => true,
]);
$inchargeRes = curl_exec($chIncharge);
$inchargeCode = curl_getinfo($chIncharge, CURLINFO_HTTP_CODE);
curl_close($chIncharge);

echo "Incharge Status Code: {$inchargeCode} (Expected: 403)\n";
echo "Incharge Response: {$inchargeRes}\n";

echo "\n=== ITEM 3.3: POST /workstations/1/enroll-token as Admin without CSRF token ===\n";
$chNoCsrf = curl_init("http://127.0.0.1:8088/workstations/1/enroll-token");
curl_setopt_array($chNoCsrf, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([]), // Missing csrf_token!
    CURLOPT_HTTPHEADER => [
        "Cookie: PHPSESSID={$adminAuth['sessId']}",
        'Accept: application/json',
        'X-Requested-With: XMLHttpRequest',
    ],
    CURLOPT_RETURNTRANSFER => true,
]);
$noCsrfRes = curl_exec($chNoCsrf);
$noCsrfCode = curl_getinfo($chNoCsrf, CURLINFO_HTTP_CODE);
curl_close($chNoCsrf);

echo "No-CSRF Status Code: {$noCsrfCode} (Expected: 419 or 403)\n";
echo "No-CSRF Response: {$noCsrfRes}\n";
