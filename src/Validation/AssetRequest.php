<?php

declare(strict_types=1);

namespace LabTrack\Validation;

class AssetRequest
{
    public const ALLOWED_TYPES = [
        'PC', 'Monitor', 'Keyboard', 'Mouse', 'CPU',
        'Printer', 'Switch', 'AccessPoint', 'Other',
    ];

    public const ALLOWED_STATUSES = [
        'Active', 'Inactive', 'Under Repair', 'Faulty', 'Retired', 'Spare', 'Decommissioned',
    ];

    /**
     * @param array $input
     * @param bool $isUpdate
     * @return array{valid: bool, errors: array<string, string>, data: array<string, mixed>}
     */
    public static function validate(array $input, bool $isUpdate = false): array
    {
        $errors = [];
        $data = [];

        $type = trim((string) ($input['asset_type'] ?? ''));
        if (!$isUpdate) {
            if ($type === '' || !in_array($type, self::ALLOWED_TYPES, true)) {
                $errors['asset_type'] = 'A valid asset type must be selected.';
            } else {
                $data['asset_type'] = $type;
            }
        } elseif ($type !== '' && in_array($type, self::ALLOWED_TYPES, true)) {
            $data['asset_type'] = $type;
        }

        $labId = trim((string) ($input['lab_id'] ?? ''));
        if ($labId === '') {
            $errors['lab_id'] = 'Lab assignment is required.';
        } else {
            $data['lab_id'] = (int) $labId;
        }

        $brandModel = trim((string) ($input['brand_model'] ?? ''));
        if (strlen($brandModel) > 100) {
            $errors['brand_model'] = 'Brand and model cannot exceed 100 characters.';
        } else {
            $data['brand_model'] = $brandModel !== '' ? $brandModel : null;
        }

        $serialNumber = trim((string) ($input['serial_number'] ?? ''));
        if (strlen($serialNumber) > 100) {
            $errors['serial_number'] = 'Serial number cannot exceed 100 characters.';
        } else {
            $data['serial_number'] = $serialNumber !== '' ? $serialNumber : null;
        }

        $status = trim((string) ($input['status'] ?? 'Active'));
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            $errors['status'] = 'Invalid asset status.';
        } else {
            $data['status'] = $status;
        }

        $purchaseDate = trim((string) ($input['purchase_date'] ?? ''));
        if ($purchaseDate !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchaseDate)) {
                $errors['purchase_date'] = 'Purchase date must be formatted YYYY-MM-DD.';
            } else {
                $data['purchase_date'] = $purchaseDate;
            }
        } else {
            $data['purchase_date'] = null;
        }

        $warrantyUntil = trim((string) ($input['warranty_until'] ?? ''));
        if ($warrantyUntil !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $warrantyUntil)) {
                $errors['warranty_until'] = 'Warranty expiration date must be formatted YYYY-MM-DD.';
            } else {
                $data['warranty_until'] = $warrantyUntil;
            }
        } else {
            $data['warranty_until'] = null;
        }

        $purchaseCost = $input['purchase_cost'] ?? '';
        if ($purchaseCost !== '' && $purchaseCost !== null) {
            if (!is_numeric($purchaseCost) || (float) $purchaseCost < 0) {
                $errors['purchase_cost'] = 'Purchase cost must be a positive number.';
            } else {
                $data['purchase_cost'] = (float) $purchaseCost;
            }
        } else {
            $data['purchase_cost'] = null;
        }

        $rawSpecs = $input['specifications'] ?? $input['specs'] ?? null;
        if (is_string($rawSpecs) && trim($rawSpecs) !== '') {
            $decoded = json_decode($rawSpecs, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                $errors['specifications'] = 'Specifications must be a valid JSON object.';
                $data['specifications'] = [];
                $data['specs'] = [];
            } else {
                $data['specifications'] = $decoded;
                $data['specs'] = $decoded;
            }
        } elseif (is_array($rawSpecs)) {
            $filtered = array_filter($rawSpecs, fn ($v) => $v !== null && $v !== '');
            $data['specifications'] = $filtered;
            $data['specs'] = $filtered;
        } else {
            $data['specifications'] = [];
            $data['specs'] = [];
        }

        $qrPayload = trim((string) ($input['qr_payload'] ?? ''));
        $data['qr_payload'] = $qrPayload !== '' ? $qrPayload : null;

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $data,
        ];
    }
}
