<?php
require_once __DIR__ . '/../includes/security.php';
startSecureSession();

function generateCaptcha(): string
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $captcha = '';
    $maxIndex = strlen($characters) - 1;

    for ($i = 0; $i < 6; $i++) {
        $captcha .= $characters[random_int(0, $maxIndex)];
    }

    return $captcha;
}

$_SESSION['captcha'] = generateCaptcha();

echo $_SESSION['captcha'];
