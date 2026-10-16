<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

use LabTrack\Repository\TelemetryRepository;

$repo = new TelemetryRepository();
$specs = $repo->getAssignedHardwareSpecs(1);
echo "Assigned specs for Workstation #1:\n";
print_r($specs);

$cred = $repo->findCredentialByWorkstationId(1);
echo "Credentials for Workstation #1:\n";
print_r($cred);
