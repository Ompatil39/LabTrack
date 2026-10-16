<?php

$ch = curl_init("http://127.0.0.1:8088/login");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
]);
$res1 = (string) curl_exec($ch);
curl_close($ch);

preg_match('/Set-Cookie:\s*PHPSESSID=([^;]+)/i', $res1, $mSess);
$sessId = $mSess[1] ?? '';
preg_match('/name="csrf_token"\s+value="([^"]+)"/i', $res1, $mCsrf);
$csrf = $mCsrf[1] ?? '';

echo "Captured Session ID: {$sessId}\n";
echo "Captured CSRF Token: {$csrf}\n";

$ch2 = curl_init("http://127.0.0.1:8088/login");
curl_setopt_array($ch2, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'username' => 'admin',
        'password' => 'admin1234',
        'csrf_token' => $csrf,
    ]),
    CURLOPT_HTTPHEADER => [
        "Cookie: PHPSESSID={$sessId}",
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
]);
$res2 = (string) curl_exec($ch2);
$code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "Login Response Code: {$code2}\n";
echo "Login Response (first 300 chars):\n" . substr($res2, 0, 300) . "\n";
