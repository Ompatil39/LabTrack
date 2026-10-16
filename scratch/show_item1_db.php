<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

$pdo = db_pdo();

echo "--- WORKSTATION #5 LIVE ROW ---\n";
$ws = $pdo->query("SELECT workstation_id, mac_address, ip_address, last_seen_at, logged_in_user, uptime_seconds, latest_cpu_pct, latest_ram_pct, latest_disk_pct, cpu_temp_c, smart_health, has_hardware_drift FROM workstations WHERE workstation_id = 5")->fetch(PDO::FETCH_ASSOC);
print_r($ws);

echo "\n--- LATEST TELEMETRY SAMPLE ROW ---\n";
$sample = $pdo->query("SELECT * FROM telemetry_samples WHERE workstation_id = 5 ORDER BY sample_id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
print_r($sample);

echo "\n--- WORKSTATION INVENTORY SNAPSHOT ROW ---\n";
$snap = $pdo->query("SELECT snapshot_id, workstation_id, updated_at, JSON_LENGTH(reported_hardware) as hw_keys_count, JSON_LENGTH(installed_software) as sw_count FROM workstation_inventory_snapshots WHERE workstation_id = 5")->fetch(PDO::FETCH_ASSOC);
print_r($snap);

$hwJson = $pdo->query("SELECT reported_hardware FROM workstation_inventory_snapshots WHERE workstation_id = 5")->fetchColumn();
echo "Hardware Snapshot (sample):\n" . substr((string) $hwJson, 0, 300) . "...\n";
