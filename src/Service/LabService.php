<?php

declare(strict_types=1);

namespace LabTrack\Service;

use InvalidArgumentException;
use LabTrack\Repository\LabRepository;
use RuntimeException;

class LabService
{
    private LabRepository $labRepository;

    public function __construct(?LabRepository $labRepository = null)
    {
        $this->labRepository = $labRepository ?? new LabRepository();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLabsForUser(?int $uid, bool $isAdmin, string $username, string $fullName = ''): array
    {
        return $this->labRepository->findAll($uid, $isAdmin, $username, $fullName);
    }

    public function getLabDetails(string $labId, ?int $uid, bool $isAdmin, string $username, string $fullName = ''): ?array
    {
        if (!$this->canAccessLab($labId, $uid, $isAdmin, $username, $fullName)) {
            throw new RuntimeException('Access denied to lab: ' . $labId);
        }

        $lab = $this->labRepository->findById($labId);
        if ($lab === null) {
            return null;
        }

        $stats = $this->labRepository->getLabDeviceStats($labId);
        $lab['stats'] = $stats;

        return $lab;
    }

    public function canAccessLab(string $labId, ?int $uid, bool $isAdmin, string $username, string $fullName = ''): bool
    {
        return $this->labRepository->canUserAccessLab($labId, $uid, $isAdmin, $username, $fullName);
    }

    public function createLab(array $data): bool
    {
        $labId = (string) ($data['lab_id'] ?? '');
        if ($labId === '') {
            throw new InvalidArgumentException('Lab ID cannot be empty.');
        }

        if ($this->labRepository->exists($labId)) {
            throw new InvalidArgumentException("A lab with ID '{$labId}' already exists.");
        }

        $created = $this->labRepository->create($data);
        if ($created) {
            AuditService::record('CREATE', 'lab', $labId, null, $data);
        }

        return $created;
    }

    public function updateLab(string $labId, array $data): bool
    {
        if (!$this->labRepository->exists($labId)) {
            throw new InvalidArgumentException("Lab '{$labId}' does not exist.");
        }

        $old = $this->labRepository->findById($labId);
        $updated = $this->labRepository->update($labId, $data);
        if ($updated) {
            AuditService::record('UPDATE', 'lab', $labId, $old, $data);
        }

        return $updated;
    }

    public function getAvailableIncharges(): array
    {
        return $this->labRepository->getAvailableIncharges();
    }
}
