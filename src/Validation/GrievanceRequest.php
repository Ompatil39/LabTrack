<?php

declare(strict_types=1);

namespace LabTrack\Validation;

class GrievanceRequest
{
    /** Valid grievance statuses (mirrors DB ENUM). */
    public const ALLOWED_STATUSES = ['Submitted', 'In Progress', 'Under Review', 'Resolved', 'Closed'];

    /** Valid device categories (mirrors DB ENUM). */
    public const ALLOWED_CATEGORIES = ['PC', 'Printer', 'Mouse', 'Keyboard', 'Monitor', 'CPU', 'Other'];

    /**
     * Validate the public grievance submission form.
     *
     * @param  array $data  Raw POST data
     * @return array        ['errors' => string[], 'clean' => array]
     */
    public function validateSubmit(array $data): array
    {
        $errors = [];
        $clean = [];

        // submitted_by — student name
        $name = trim((string) ($data['submitted_by'] ?? ''));
        if ($name === '') {
            $errors[] = 'Your name is required.';
        } elseif (mb_strlen($name) > 100) {
            $errors[] = 'Name must not exceed 100 characters.';
        } else {
            $clean['submitted_by'] = $name;
        }

        // stud_enrollment
        $enrollment = trim((string) ($data['stud_enrollment'] ?? ''));
        if ($enrollment === '') {
            $errors[] = 'Enrollment number is required.';
        } elseif (!preg_match('/^[A-Za-z0-9\-\/]{3,20}$/', $enrollment)) {
            $errors[] = 'Enrollment number must be 3–20 alphanumeric characters (hyphens/slashes allowed).';
        } else {
            $clean['stud_enrollment'] = strtoupper($enrollment);
        }

        // class — e.g. CO-5I
        $class = trim((string) ($data['class'] ?? ''));
        if ($class === '') {
            $errors[] = 'Class is required.';
        } elseif (!preg_match('/^[A-Za-z0-9\-]{2,10}$/', $class)) {
            $errors[] = 'Class must be 2–10 alphanumeric characters (hyphens allowed), e.g. CO-5I.';
        } else {
            $clean['class'] = strtoupper($class);
        }

        // stud_email
        $email = trim((string) ($data['stud_email'] ?? ''));
        if ($email === '') {
            $errors[] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        } elseif (mb_strlen($email) > 100) {
            $errors[] = 'Email must not exceed 100 characters.';
        } else {
            $clean['stud_email'] = strtolower($email);
        }

        // lab_id
        $labId = trim((string) ($data['lab_id'] ?? ''));
        if ($labId === '') {
            $errors[] = 'Please select a lab.';
        } elseif (mb_strlen($labId) > 20) {
            $errors[] = 'Lab ID is invalid.';
        } else {
            $clean['lab_id'] = $labId;
        }

        // device_category — required
        $cat = trim((string) ($data['device_category'] ?? ''));
        if ($cat === '' || !in_array($cat, self::ALLOWED_CATEGORIES, true)) {
            $errors[] = 'Please select a valid device category (' . implode(', ', self::ALLOWED_CATEGORIES) . ').';
        } else {
            $clean['device_category'] = $cat;
        }

        // device_name — optional
        $deviceName = trim((string) ($data['device_name'] ?? ''));
        $clean['device_name'] = mb_substr($deviceName, 0, 100);

        // device_id (legacy) — optional free-text identifier
        $deviceId = trim((string) ($data['device_id'] ?? ''));
        $clean['device_id'] = mb_substr($deviceId, 0, 20) ?: null;

        // asset_id (Phase 2) — optional integer FK
        $assetId = $data['asset_id'] ?? null;
        $clean['asset_id'] = ($assetId !== null && $assetId !== '') ? (int) $assetId : null;

        // workstation_id (Phase 2) — optional integer FK
        $wsId = $data['workstation_id'] ?? null;
        $clean['workstation_id'] = ($wsId !== null && $wsId !== '') ? (int) $wsId : null;

        // grievance_desc — required, non-empty
        $desc = trim((string) ($data['grievance_desc'] ?? ''));
        if ($desc === '') {
            $errors[] = 'Grievance description is required.';
        } elseif (mb_strlen($desc) > 5000) {
            $errors[] = 'Description must not exceed 5000 characters.';
        } else {
            $clean['grievance_desc'] = $desc;
        }

        return ['errors' => $errors, 'clean' => $clean];
    }

    /**
     * Validate a status-update action (staff/admin action).
     *
     * @param  array $data  Raw POST data
     * @return array        ['errors' => string[], 'clean' => array]
     */
    public function validateStatusUpdate(array $data): array
    {
        $errors = [];
        $clean = [];

        $status = trim((string) ($data['status'] ?? ''));
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            $errors[] = 'Invalid status. Allowed: ' . implode(', ', self::ALLOWED_STATUSES) . '.';
        } else {
            $clean['status'] = $status;
        }

        // admin_note is optional
        $note = trim((string) ($data['admin_note'] ?? ''));
        if ($note !== '' && mb_strlen($note) > 2000) {
            $errors[] = 'Admin note must not exceed 2000 characters.';
        } else {
            $clean['admin_note'] = $note !== '' ? $note : null;
        }

        return ['errors' => $errors, 'clean' => $clean];
    }

    /**
     * Static wrapper returning ['valid' => bool, 'errors' => array, 'data' => array].
     */
    public static function validate(array $data): array
    {
        $res = (new self())->validateSubmit($data);
        return [
            'valid' => empty($res['errors']),
            'errors' => $res['errors'],
            'data' => $res['clean'],
        ];
    }

    /**
     * Static wrapper for status updates.
     */
    public static function validateStatus(array $data): array
    {
        $res = (new self())->validateStatusUpdate($data);
        return [
            'valid' => empty($res['errors']),
            'errors' => $res['errors'],
            'data' => $res['clean'],
        ];
    }
}
