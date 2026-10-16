<?php

declare(strict_types=1);

namespace LabTrack\Validation;

class WorkstationRequest
{
    public const ALLOWED_STATUSES = ['Active', 'Inactive', 'Under Repair'];

    /**
     * @param array $input Raw form data
     * @return array{valid: bool, errors: array<string, string>, data: array<string, mixed>}
     */
    public static function validateCreate(array $input): array
    {
        $errors = [];
        $data = [];

        $labId = trim((string) ($input['lab_id'] ?? ''));
        if ($labId === '' || !is_numeric($labId) || (int) $labId <= 0) {
            $errors['lab_id'] = 'Valid Lab ID is required.';
        } else {
            $data['lab_id'] = (int) $labId;
        }

        $seatCode = trim((string) ($input['seat_code'] ?? ''));
        if ($seatCode === '') {
            $errors['seat_code'] = 'Seat code is required (e.g. A-01, WS-12).';
        } elseif (strlen($seatCode) > 20) {
            $errors['seat_code'] = 'Seat code cannot exceed 20 characters.';
        } else {
            $data['seat_code'] = $seatCode;
        }

        $hostname = trim((string) ($input['hostname'] ?? ''));
        $data['hostname'] = $hostname !== '' ? $hostname : null;

        $status = trim((string) ($input['status'] ?? 'Active'));
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            $errors['status'] = 'Invalid workstation status.';
        } else {
            $data['status'] = $status;
        }

        // Check PC model requirement
        $pcModel = trim((string) ($input['pc_model'] ?? $input['parts']['pc']['brand_model'] ?? ''));
        if ($pcModel === '') {
            $errors['pc_model'] = 'PC model is required.';
        }

        // Build PC specifications
        $pcSpecs = [];
        if (isset($input['ram']) && trim((string) $input['ram']) !== '') {
            $pcSpecs['ram'] = trim((string) $input['ram']);
        }
        if (isset($input['storage']) && trim((string) $input['storage']) !== '') {
            $pcSpecs['storage'] = trim((string) $input['storage']);
        }
        if (isset($input['os']) && trim((string) $input['os']) !== '') {
            $pcSpecs['os'] = trim((string) $input['os']);
        }
        if (isset($input['cpu_model']) && trim((string) $input['cpu_model']) !== '') {
            $pcSpecs['cpu'] = trim((string) $input['cpu_model']);
        }
        if (isset($input['parts']['pc']['specs']) && is_array($input['parts']['pc']['specs'])) {
            $pcSpecs = array_merge($pcSpecs, $input['parts']['pc']['specs']);
        }
        $data['pc_specs'] = $pcSpecs;

        // Parts data (PC, Monitor, Keyboard, Mouse, CPU)
        $parts = [];
        $partTypes = ['PC', 'Monitor', 'Keyboard', 'Mouse', 'CPU'];

        foreach ($partTypes as $type) {
            $key = strtolower($type);
            $partData = $input['parts'][$key] ?? [];

            $brandModel = trim((string) ($partData['brand_model'] ?? $input[$key . '_model'] ?? ''));
            $serialNumber = trim((string) ($partData['serial_number'] ?? $input[$key . '_serial'] ?? ''));
            $partStatus = trim((string) ($partData['status'] ?? 'Active'));
            $specs = is_array($partData['specs'] ?? null) ? $partData['specs'] : ($type === 'PC' ? $pcSpecs : []);

            $parts[] = [
                'asset_type' => $type,
                'brand_model' => $brandModel !== '' ? $brandModel : ($type . ' Component'),
                'serial_number' => $serialNumber !== '' ? $serialNumber : null,
                'status' => in_array($partStatus, ['Active', 'Inactive', 'Under Repair', 'Faulty'], true) ? $partStatus : 'Active',
                'specs' => array_filter($specs, fn ($v) => $v !== null && $v !== ''),
            ];
        }

        $data['parts'] = $parts;

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $data,
        ];
    }

    /**
     * @param array $input Raw form data
     * @return array{valid: bool, errors: array<string, string>, data: array<string, mixed>}
     */
    public static function validateUpdate(array $input): array
    {
        $errors = [];
        $data = [];

        $seatCode = trim((string) ($input['seat_code'] ?? ''));
        if ($seatCode === '') {
            $errors['seat_code'] = 'Seat code is required.';
        } else {
            $data['seat_code'] = $seatCode;
        }

        $hostname = trim((string) ($input['hostname'] ?? ''));
        $data['hostname'] = $hostname !== '' ? $hostname : null;

        $status = trim((string) ($input['status'] ?? 'Active'));
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            $errors['status'] = 'Invalid workstation status.';
        } else {
            $data['status'] = $status;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $data,
        ];
    }

    /**
     * @param array $input Raw form data
     * @return array{valid: bool, errors: array<string, string>, data: array<string, mixed>}
     */
    public static function validateSwap(array $input): array
    {
        $errors = [];
        $data = [];

        $oldAssetId = (int) ($input['old_asset_id'] ?? 0);
        $newAssetId = (int) ($input['new_asset_id'] ?? 0);

        if ($oldAssetId <= 0) {
            $errors['old_asset_id'] = 'Valid current installed asset must be selected.';
        }
        if ($newAssetId <= 0) {
            $errors['new_asset_id'] = 'Valid replacement asset must be selected.';
        } elseif ($oldAssetId > 0 && $oldAssetId === $newAssetId) {
            $errors['new_asset_id'] = 'Replacement asset cannot be identical to current asset.';
        }

        $reason = trim((string) ($input['reason'] ?? $input['removal_reason'] ?? ''));
        if ($reason === '') {
            $errors['reason'] = 'Swap reason is required.';
            $errors['removal_reason'] = 'Swap reason is required.';
        } elseif (strlen($reason) < 3) {
            $errors['reason'] = 'Please provide a descriptive reason for swapping (at least 3 characters).';
            $errors['removal_reason'] = 'Please provide a descriptive reason for swapping (at least 3 characters).';
        }

        $oldStatus = trim((string) ($input['old_status'] ?? 'Faulty'));
        $validStatuses = ['Faulty', 'Under Repair', 'Inactive', 'Retired', 'Active'];
        if (!in_array($oldStatus, $validStatuses, true)) {
            $oldStatus = 'Faulty';
        }

        $data['old_asset_id'] = $oldAssetId;
        $data['new_asset_id'] = $newAssetId;
        $data['reason'] = $reason;
        $data['removal_reason'] = $reason;
        $data['old_status'] = $oldStatus;

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $data,
        ];
    }
}
