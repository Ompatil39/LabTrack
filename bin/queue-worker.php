<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use LabTrack\Service\GrievanceService;
use LabTrack\Service\QueueService;

// Load environment variables if available
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

require_once __DIR__ . '/../config/config.php';

$options = getopt('', ['once', 'sla-check', 'help']);

if (isset($options['help'])) {
    echo "Usage: php bin/queue-worker.php [options]\n";
    echo "Options:\n";
    echo "  --once       Process pending jobs once and exit\n";
    echo "  --sla-check  Run SLA breach check and escalate overdue tickets\n";
    echo "  --help       Show this help message\n";
    exit(0);
}

$queueService = new QueueService();
$grievanceService = new GrievanceService();

$runSlaCheck = isset($options['sla-check']);
$runOnce = isset($options['once']);

echo '[' . date('Y-m-d H:i:s') . "] Starting LabTrack Queue Worker...\n";

if ($runSlaCheck) {
    echo '[' . date('Y-m-d H:i:s') . "] Running SLA escalation check...\n";
    $escalated = $grievanceService->escalateBreachedSlas();
    echo '[' . date('Y-m-d H:i:s') . "] Escalated {$escalated} ticket(s) to Admin.\n";
}

$processed = $queueService->work($runOnce);
echo '[' . date('Y-m-d H:i:s') . "] Processed {$processed} job(s).\n";

if (!$runOnce) {
    echo '[' . date('Y-m-d H:i:s') . "] Worker listening for jobs (Ctrl+C to stop)...\n";
    $iterations = 0;
    while ($iterations < 100) {
        sleep(2);
        $count = $queueService->work(true);
        if ($count > 0) {
            echo '[' . date('Y-m-d H:i:s') . "] Processed {$count} job(s).\n";
        }
        $iterations++;
    }
}

echo '[' . date('Y-m-d H:i:s') . "] Worker stopped.\n";
