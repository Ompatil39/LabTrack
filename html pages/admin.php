<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
requireLogin();
requireRole('Admin');

header('Location: index.php');
exit;
