<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

use LabTrack\Service\TelemetryService;

$pdo = db_pdo();
$pdo->exec("DELETE FROM hardware_drift_alerts WHERE workstation_id = 5");
$pdo->exec("DELETE FROM workstation_inventory_snapshots WHERE workstation_id = 5");
$pdo->exec("DELETE FROM telemetry_samples WHERE workstation_id = 5");
$pdo->exec("DELETE FROM agent_credentials WHERE workstation_id = 5");

$ts = new TelemetryService();
$tokenData = $ts->createEnrollmentToken(5, 1);
echo $tokenData['token'];
