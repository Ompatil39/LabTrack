<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

use LabTrack\Service\TelemetryService;

echo "=== LabTrack Telemetry Retention & Rollups Job ===\n";
echo 'Started at: ' . date('Y-m-d H:i:s') . "\n";

try {
    $service = new TelemetryService();
    $results = $service->runRetentionAndRollups();

    echo "Hourly rollups processed/created: {$results['rollups_created']}\n";
    echo "Raw samples purged (>30d):        {$results['samples_purged']}\n";
    echo "Old rollups purged (>365d):       {$results['rollups_purged']}\n";
    echo "Old nonces purged (>10m):         {$results['nonces_purged']}\n";
    echo "Retention job completed successfully.\n";
    exit(0);
} catch (\Throwable $e) {
    echo 'Retention job failed: ' . $e->getMessage() . "\n";
    exit(1);
}
