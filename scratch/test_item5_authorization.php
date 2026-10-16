<?php

declare(strict_types=1);

function loginIncharge(string $username, string $password): string
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
    return $sessId;
}

$sessId = loginIncharge('prof_sharma', 'admin1234'); // incharge of LAB-101

echo "=== ITEM 5.1: prof_sharma requesting telemetry for Workstation #1 (Assigned Lab LAB-101) ===\n";
$chAssigned = curl_init("http://127.0.0.1:8088/api/workstations/1/telemetry");
curl_setopt_array($chAssigned, [
    CURLOPT_HTTPHEADER => [
        "Cookie: PHPSESSID={$sessId}",
        'Accept: application/json',
    ],
    CURLOPT_RETURNTRANSFER => true,
]);
$resAssigned = curl_exec($chAssigned);
$codeAssigned = curl_getinfo($chAssigned, CURLINFO_HTTP_CODE);
curl_close($chAssigned);
echo "Status: {$codeAssigned} (Expected: 200)\n";
echo "Response snippet: " . substr((string) $resAssigned, 0, 160) . "...\n\n";

echo "=== ITEM 5.2: prof_sharma requesting telemetry for Workstation #11 (Different Lab LAB-102) ===\n";
$chDifferent = curl_init("http://127.0.0.1:8088/api/workstations/11/telemetry");
curl_setopt_array($chDifferent, [
    CURLOPT_HTTPHEADER => [
        "Cookie: PHPSESSID={$sessId}",
        'Accept: application/json',
    ],
    CURLOPT_RETURNTRANSFER => true,
]);
$resDifferent = curl_exec($chDifferent);
$codeDifferent = curl_getinfo($chDifferent, CURLINFO_HTTP_CODE);
curl_close($chDifferent);
echo "Status: {$codeDifferent} (Expected: 403)\n";
echo "Response Body: {$resDifferent}\n";
