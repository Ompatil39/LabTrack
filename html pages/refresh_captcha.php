<?php
// Start the session
session_start();

// Generate a new CAPTCHA
function generateCaptcha()
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $captcha = '';
    for ($i = 0; $i < 2; $i++) {
        $captcha .= $characters[rand(0, strlen($characters) - 1)];
    }
    $_SESSION['captcha'] = $captcha;
    return $captcha;
}

// Generate and return the new CAPTCHA
echo generateCaptcha();
?>