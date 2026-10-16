<?php

declare(strict_types=1);

namespace LabTrack\Service;

use InvalidArgumentException;
use LabTrack\Repository\AssetRepository;
use LabTrack\Repository\LabRepository;
use LabTrack\Repository\WorkstationRepository;
use LabTrack\WorkstationService;
use RuntimeException;

class WorkstationAppService
{
    private WorkstationRepository $workstationRepository;
    private AssetRepository $assetRepository;
    private LabRepository $labRepository;

    public function __construct(
        ?WorkstationRepository $workstationRepository = null,
        ?AssetRepository $assetRepository = null,
        ?LabRepository $labRepository = null
    ) {
        $this->workstationRepository = $workstationRepository ?? new WorkstationRepository();
        $this->assetRepository = $assetRepository ?? new AssetRepository();
        $this->labRepository = $labRepository ?? new LabRepository();
    }

    public function canUserAccessLab(
        string $labId,
        ?int $userUid,
        bool $isAdmin,
        string $username = '',
        string $fullName = ''
    ): bool {
        if ($isAdmin) {
            return true;
        }
        return $this->labRepository->canUserAccessLab($labId, $userUid, $isAdmin, $username, $fullName);
    }

    public function getWorkstations(
        ?string $labId,
        ?int $userUid,
        bool $isAdmin,
        string $username,
        string $fullName = ''
    ): array {
        if ($labId !== null && $labId !== '') {
            if (!$this->canUserAccessLab($labId, $userUid, $isAdmin, $username, $fullName)) {
                throw new RuntimeException("Access denied to lab '{$labId}'.");
            }
        }

        return $this->workstationRepository->findAll($labId, $userUid, $isAdmin, $username, $fullName);
    }

    public function getWorkstationDetails(
        int $workstationId,
        ?int $userUid,
        bool $isAdmin,
        string $username,
        string $fullName = ''
    ): ?array {
        $ws = $this->workstationRepository->findById($workstationId);
        if ($ws === null) {
            return null;
        }

        $labId = (string) $ws['lab_id'];
        if (!$this->labRepository->canUserAccessLab($labId, $userUid, $isAdmin, $username, $fullName)) {
            throw new RuntimeException("Access denied to workstation #{$workstationId}.");
        }

        // Reuse Phase 2 WorkstationService::getWorkstationDetails if mysqli connection is active
        $mysqli = function_exists('db_mysqli') ? db_mysqli() : null;
        if ($mysqli !== null) {
            $details = WorkstationService::getWorkstationDetails($mysqli, $workstationId);
            if ($details !== null) {
                $details['available_spares'] = $this->workstationRepository->getAvailableSparesForWorkstation($workstationId, $labId);
                return $details;
            }
        }

        // Fallback / offline representation
        $currentParts = $this->assetRepository->findAll(['workstation_id' => $workstationId]);
        $spares = $this->workstationRepository->getAvailableSparesForWorkstation($workstationId, $labId);

        return [
            'workstation' => $ws,
            'current_parts' => $currentParts,
            'history' => [],
            'grievances' => [],
            'available_spares' => $spares,
        ];
    }

    public function createWorkstationWithParts(array $wsData, array $partsData): array
    {
        $labId = (string) ($wsData['lab_id'] ?? '');
        $seatCode = (string) ($wsData['seat_code'] ?? '');

        if ($labId === '' || $seatCode === '') {
            throw new InvalidArgumentException('lab_id and seat_code are required.');
        }

        // Check if seat already exists in lab
        $existing = $this->workstationRepository->findByLabAndSeat($labId, $seatCode);
        if ($existing !== null) {
            throw new InvalidArgumentException("Workstation seat '{$seatCode}' already exists in lab '{$labId}'.");
        }

        $mysqli = function_exists('db_mysqli') ? db_mysqli() : null;
        if ($mysqli !== null) {
            // Reuses Phase 2 atomic transaction & tag generation
            return WorkstationService::createWorkstationWithParts($mysqli, $wsData, $partsData);
        }

        // If mysqli is not available (mock/testing environment without live db), delegate basic record
        $wsId = $this->workstationRepository->create($wsData);
        return [
            'workstation_id' => $wsId,
            'assets' => [],
        ];
    }

    public function swapPart(
        int $workstationId,
        int $oldAssetId,
        int $newAssetId,
        string $reason,
        string $oldStatus,
        ?int $userUid,
        bool $isAdmin,
        string $username,
        string $fullName = ''
    ): void {
        $ws = $this->workstationRepository->findById($workstationId);
        if ($ws === null) {
            throw new InvalidArgumentException("Workstation #{$workstationId} not found.");
        }

        $labId = (string) $ws['lab_id'];
        if (!$this->labRepository->canUserAccessLab($labId, $userUid, $isAdmin, $username, $fullName)) {
            throw new RuntimeException("Access denied to workstation #{$workstationId}.");
        }

        $mysqli = function_exists('db_mysqli') ? db_mysqli() : null;
        if ($mysqli !== null) {
            WorkstationService::swapWorkstationPart(
                $mysqli,
                $workstationId,
                $oldAssetId,
                $newAssetId,
                $reason,
                $oldStatus
            );
            return;
        }

        throw new RuntimeException('Database connection required to execute atomic part swap.');
    }

    public function updateWorkstation(int $workstationId, array $data): bool
    {
        $ws = $this->workstationRepository->findById($workstationId);
        if ($ws === null) {
            throw new InvalidArgumentException("Workstation #{$workstationId} not found.");
        }

        return $this->workstationRepository->update($workstationId, $data);
    }
}
