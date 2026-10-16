<?php

declare(strict_types=1);

namespace LabTrack\Validation;

class MaintenanceRequest
{
    public const ALLOWED_TYPES = ['Repair', 'Preventive', 'Inspection', 'Upgrade'];

    /**
     * @param array<string, mixed> $input
     * @return array{valid: bool, errors: array<string, string>, data: array<string, mixed>}
     */
    public function validate(array $input): array
    {
        $errors = [];
        $data = [];

        // asset_id
        $assetId = isset($input['asset_id']) ? (int) $input['asset_id'] : 0;
        if ($assetId <= 0) {
            $errors['asset_id'] = 'Valid Asset ID is required.';
        }
        $data['asset_id'] = $assetId;

        // maintenance_type
        $type = trim((string) ($input['maintenance_type'] ?? ''));
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            $errors['maintenance_type'] = 'Maintenance type must be one of: ' . implode(', ', self::ALLOWED_TYPES);
        }
        $data['maintenance_type'] = $type;

        // title
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Title is required.';
        } elseif (mb_strlen($title) > 150) {
            $errors['title'] = 'Title cannot exceed 150 characters.';
        }
        $data['title'] = $title;

        // optional fields
        $data['workstation_id'] = !empty($input['workstation_id']) ? (int) $input['workstation_id'] : null;
        $data['lab_id'] = trim((string) ($input['lab_id'] ?? ''));
        $data['description'] = !empty($input['description']) ? trim((string) $input['description']) : null;
        $data['vendor'] = !empty($input['vendor']) ? trim((string) $input['vendor']) : null;

        // cost
        $cost = $input['cost'] ?? 0.00;
        if (!is_numeric($cost) || (float) $cost < 0) {
            $errors['cost'] = 'Cost must be a non-negative number.';
        }
        $data['cost'] = (float) $cost;

        // started_at
        $startedAt = trim((string) ($input['started_at'] ?? ''));
        if ($startedAt === '') {
            $startedAt = date('Y-m-d H:i:s');
        } elseif (strtotime($startedAt) === false) {
            $errors['started_at'] = 'Started at must be a valid date/time.';
        }
        $data['started_at'] = $startedAt;

        // completed_at
        $completedAt = !empty($input['completed_at']) ? trim((string) $input['completed_at']) : null;
        if ($completedAt !== null) {
            if (strtotime($completedAt) === false) {
                $errors['completed_at'] = 'Completed at must be a valid date/time.';
            } elseif (strtotime($completedAt) < strtotime($startedAt)) {
                $errors['completed_at'] = 'Completed at cannot be before started at.';
            }
        }
        $data['completed_at'] = $completedAt;

        // next_scheduled_date
        $nextDate = !empty($input['next_scheduled_date']) ? trim((string) $input['next_scheduled_date']) : null;
        if ($nextDate !== null && strtotime($nextDate) === false) {
            $errors['next_scheduled_date'] = 'Next scheduled date must be a valid date.';
        }
        $data['next_scheduled_date'] = $nextDate;

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $data,
        ];
    }
}
