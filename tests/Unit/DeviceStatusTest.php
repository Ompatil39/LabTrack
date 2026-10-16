<?php

declare(strict_types=1);

namespace LabTrack\Tests\Unit;

use LabTrack\Support\DeviceStatus;
use PHPUnit\Framework\TestCase;

class DeviceStatusTest extends TestCase
{
    public function testCanonicalStatusConstants(): void
    {
        $this->assertSame('Active', DeviceStatus::ACTIVE);
        $this->assertSame('Inactive', DeviceStatus::INACTIVE);
        $this->assertSame('Under Repair', DeviceStatus::UNDER_REPAIR);
        $this->assertSame('Faulty', DeviceStatus::FAULTY);
        $this->assertSame('Retired', DeviceStatus::RETIRED);
    }

    public function testNormalizationOfLegacyAndMixedCaseStatuses(): void
    {
        $this->assertSame(DeviceStatus::ACTIVE, DeviceStatus::normalize('active'));
        $this->assertSame(DeviceStatus::ACTIVE, DeviceStatus::normalize('ACTIVE'));
        $this->assertSame(DeviceStatus::INACTIVE, DeviceStatus::normalize('inactive'));
        $this->assertSame(DeviceStatus::INACTIVE, DeviceStatus::normalize('InActive'));
        $this->assertSame(DeviceStatus::UNDER_REPAIR, DeviceStatus::normalize('under repair'));
        $this->assertSame(DeviceStatus::UNDER_REPAIR, DeviceStatus::normalize('in repair'));
        $this->assertSame(DeviceStatus::FAULTY, DeviceStatus::normalize('faulty'));
        $this->assertSame(DeviceStatus::RETIRED, DeviceStatus::normalize('retired'));
    }

    public function testUnknownStatusPreservedByDefault(): void
    {
        $this->assertSame('CustomStatus', DeviceStatus::normalize('CustomStatus'));
    }
}
