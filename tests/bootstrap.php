<?php

declare(strict_types=1);

define('PHPUNIT_RUNNING', true);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/security.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
