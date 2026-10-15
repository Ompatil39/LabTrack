<?php

declare(strict_types=1);

namespace LabTrack;

final class DeviceStatus
{
    public const ACTIVE = 'Active';
    public const INACTIVE = 'Inactive';
    public const UNDER_REPAIR = 'Under Repair';
    public const FAULTY = 'Faulty';
    public const RETIRED = 'Retired';

    public const LEGACY_INACTIVE = 'InActive';

    public static function normalize(string $status): string
    {
        $normalized = strtolower(trim($status));

        return match ($normalized) {
            'active' => self::ACTIVE,
            'inactive', strtolower(self::LEGACY_INACTIVE) => self::INACTIVE,
            'in repair', 'under repair' => self::UNDER_REPAIR,
            'faulty' => self::FAULTY,
            'retired' => self::RETIRED,
            default => $status,
        };
    }
}
