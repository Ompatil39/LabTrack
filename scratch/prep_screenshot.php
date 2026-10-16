<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

$tele = new LabTrack\Service\TelemetryService();
$tele->recordInventoryAndDetectDrift(1, [
    'ram_gb' => 8,
    'cpu_cores' => 8,
    'cpu_model' => 'Intel Core i7-12700',
    'disk_gb' => 512,
    'os_name' => 'Windows 11 Pro',
], []);

$pdo = db_pdo();
$pdo->exec("UPDATE workstations SET 
    last_seen_at = NOW(), 
    latest_cpu_pct = 34.2, 
    latest_ram_pct = 68.5, 
    latest_disk_pct = 45.0, 
    cpu_temp_c = 42.0, 
    smart_health = 'HEALTHY' 
WHERE workstation_id = 1");

echo "Workstation 1 prepared successfully: Online + Open Drift Alert + Telemetry Bars\n";
