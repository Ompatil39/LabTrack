<?php

declare(strict_types=1);

namespace LabTrack\Validation;

class LabRequest
{
    public const ALLOWED_STATUSES = ['Active', 'InActive', 'In Repair'];

    /**
     * @param array $input Raw input data (e.g. $_POST)
     * @param bool $isUpdate True if validating for an update (where lab_id cannot be changed)
     * @return array{valid: bool, errors: array<string, string>, data: array<string, mixed>}
     */
    public static function validate(array $input, bool $isUpdate = false): array
    {
        $errors = [];
        $data = [];

        // 1. lab_id (only validated on create)
        if (!$isUpdate) {
            $labId = trim((string) ($input['lab_id'] ?? ''));
            if ($labId === '') {
                $errors['lab_id'] = 'Lab ID is required.';
            } elseif (strlen($labId) < 2 || strlen($labId) > 20) {
                $errors['lab_id'] = 'Lab ID must be between 2 and 20 characters.';
            } elseif (!preg_match('/^[A-Za-z0-9_\-]+$/', $labId)) {
                $errors['lab_id'] = 'Lab ID can only contain alphanumeric characters, hyphens, and underscores.';
            } else {
                $data['lab_id'] = $labId;
            }
        }

        // 2. lab_name
        $labName = trim((string) ($input['lab_name'] ?? ''));
        if ($labName === '') {
            $errors['lab_name'] = 'Lab Name is required.';
        } elseif (strlen($labName) < 2 || strlen($labName) > 100) {
            $errors['lab_name'] = 'Lab Name must be between 2 and 100 characters.';
        } else {
            $data['lab_name'] = $labName;
        }

        // 3. status
        $status = trim((string) ($input['status'] ?? 'Active'));
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            $errors['status'] = 'Invalid status selected.';
        } else {
            $data['status'] = $status;
        }

        // 4. department
        $department = trim((string) ($input['department'] ?? ''));
        if ($department === '') {
            $errors['department'] = 'Department is required.';
        } elseif (strlen($department) > 100) {
            $errors['department'] = 'Department name cannot exceed 100 characters.';
        } else {
            $data['department'] = $department;
        }

        // 5. lab_incharge (string name or username)
        $labIncharge = trim((string) ($input['lab_incharge'] ?? ''));
        if ($labIncharge === '') {
            $errors['lab_incharge'] = 'Lab Incharge is required.';
        } elseif (strlen($labIncharge) > 100) {
            $errors['lab_incharge'] = 'Lab Incharge name cannot exceed 100 characters.';
        } else {
            $data['lab_incharge'] = $labIncharge;
        }

        // 6. incharge_uid (optional relational UID)
        $inchargeUid = $input['incharge_uid'] ?? null;
        if ($inchargeUid !== null && $inchargeUid !== '') {
            $data['incharge_uid'] = (int) $inchargeUid;
        } else {
            $data['incharge_uid'] = null;
        }

        // 7. establishment_date
        $establishmentDate = trim((string) ($input['establishment_date'] ?? ''));
        if ($establishmentDate === '') {
            $errors['establishment_date'] = 'Establishment date is required.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $establishmentDate)) {
            $errors['establishment_date'] = 'Establishment date must be in YYYY-MM-DD format.';
        } else {
            $data['establishment_date'] = $establishmentDate;
        }

        // 8. room_capacity
        $roomCapacity = $input['room_capacity'] ?? '';
        if ($roomCapacity === '' || !is_numeric($roomCapacity) || (int) $roomCapacity < 1) {
            $errors['room_capacity'] = 'Room capacity must be a positive integer.';
        } else {
            $data['room_capacity'] = (int) $roomCapacity;
        }

        // 9. building
        $building = trim((string) ($input['building'] ?? ''));
        if (strlen($building) > 100) {
            $errors['building'] = 'Building name cannot exceed 100 characters.';
        } else {
            $data['building'] = $building !== '' ? $building : null;
        }

        // 10. room_number
        $roomNumber = trim((string) ($input['room_number'] ?? ''));
        if ($roomNumber === '') {
            $errors['room_number'] = 'Room number is required.';
        } elseif (strlen($roomNumber) > 50) {
            $errors['room_number'] = 'Room number cannot exceed 50 characters.';
        } else {
            $data['room_number'] = $roomNumber;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $data,
        ];
    }
}
