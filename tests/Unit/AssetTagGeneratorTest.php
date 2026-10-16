<?php

declare(strict_types=1);

namespace LabTrack\Tests\Unit;

use InvalidArgumentException;
use LabTrack\Support\AssetTagGenerator;
use PHPUnit\Framework\TestCase;

class AssetTagGeneratorTest extends TestCase
{
    public function testPrefixMappingCoversAllStandardDeviceTypes(): void
    {
        $map = AssetTagGenerator::getPrefixMap();

        $this->assertArrayHasKey('PC', $map);
        $this->assertSame('PC', $map['PC']);
        $this->assertArrayHasKey('Monitor', $map);
        $this->assertSame('MON', $map['Monitor']);
        $this->assertArrayHasKey('Keyboard', $map);
        $this->assertSame('KBD', $map['Keyboard']);
        $this->assertArrayHasKey('Mouse', $map);
        $this->assertSame('MSE', $map['Mouse']);
        $this->assertArrayHasKey('CPU', $map);
        $this->assertSame('CPU', $map['CPU']);
        $this->assertArrayHasKey('Printer', $map);
        $this->assertSame('PRT', $map['Printer']);
        $this->assertArrayHasKey('Switch', $map);
        $this->assertSame('SW', $map['Switch']);
        $this->assertArrayHasKey('AccessPoint', $map);
        $this->assertSame('AP', $map['AccessPoint']);
        $this->assertArrayHasKey('Other', $map);
        $this->assertSame('AST', $map['Other']);
    }

    public function testInvalidAssetTypeThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unrecognized asset type 'InvalidType'");

        /** @var \mysqli $dummyConn */
        $dummyConn = $this->createMock(\mysqli::class);
        AssetTagGenerator::nextTag($dummyConn, 'InvalidType');
    }

    public function testTagFormattingPattern(): void
    {
        $prefix = 'MON';
        $year = 2026;
        $seq = 42;
        $formatted = sprintf('%s-%d-%04d', $prefix, $year, $seq);

        $this->assertSame('MON-2026-0042', $formatted);
        $this->assertMatchesRegularExpression('/^[A-Z]{2,4}-\d{4}-\d{4}$/', $formatted);
    }
}
