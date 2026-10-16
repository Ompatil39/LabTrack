<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

// 1. Login as admin
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

// 2. Fetch /workstations/1
$ch3 = curl_init("http://127.0.0.1:8088/workstations/1");
curl_setopt_array($ch3, [
    CURLOPT_HTTPHEADER => ["Cookie: PHPSESSID={$sessId}"],
    CURLOPT_RETURNTRANSFER => true,
]);
$html = (string) curl_exec($ch3);
curl_close($ch3);

// Replace relative assets with absolute URLs so Edge renders CSS properly
$html = str_replace('href="/css/', 'href="http://127.0.0.1:8088/css/', $html);
$html = str_replace('src="/js/', 'src="http://127.0.0.1:8088/js/', $html);
$html = str_replace('href="/assets/', 'href="http://127.0.0.1:8088/assets/', $html);
$html = str_replace('src="/assets/', 'src="http://127.0.0.1:8088/assets/', $html);

file_put_contents(__DIR__ . '/workstation_page.html', $html);
echo "Saved rendered HTML to scratch/workstation_page.html (length: " . strlen($html) . ")\n";
