<?php

declare(strict_types=1);

namespace LabTrack\Tests\Unit;

use LabTrack\Validation\AssetRequest;
use LabTrack\Validation\GrievanceRequest;
use LabTrack\Validation\LabRequest;
use LabTrack\Validation\WorkstationRequest;
use PHPUnit\Framework\TestCase;

class ValidationTest extends TestCase
{
    public function testLabRequestValidatesCorrectPayload(): void
    {
        $input = [
            'lab_id' => 'LAB-CS-01',
            'lab_name' => 'Computer Science Lab 1',
            'department' => 'CSE',
            'lab_incharge' => 'Prof. Sharma',
            'room_number' => '101',
            'status' => 'Active',
            'room_capacity' => '30',
            'establishment_date' => '2024-01-15',
        ];

        $res = LabRequest::validate($input, false);
        $this->assertTrue($res['valid']);
        $this->assertEmpty($res['errors']);
        $this->assertSame(30, $res['data']['room_capacity']);
        $this->assertSame('LAB-CS-01', $res['data']['lab_id']);
    }

    public function testLabRequestRejectsInvalidInput(): void
    {
        $input = [
            'lab_id' => 'LAB#INVALID',
            'lab_name' => '',
            'status' => 'UnknownStatus',
            'room_capacity' => '-5',
            'establishment_date' => 'not-a-date',
        ];

        $res = LabRequest::validate($input, false);
        $this->assertFalse($res['valid']);
        $this->assertArrayHasKey('lab_id', $res['errors']);
        $this->assertArrayHasKey('lab_name', $res['errors']);
        $this->assertArrayHasKey('status', $res['errors']);
        $this->assertArrayHasKey('room_capacity', $res['errors']);
        $this->assertArrayHasKey('establishment_date', $res['errors']);
    }

    public function testWorkstationRequestValidateCreateAndSwap(): void
    {
        $createInput = [
            'lab_id' => '1',
            'seat_code' => 'W-01',
            'pc_brand' => 'Dell',
            'pc_model' => 'OptiPlex 7090',
            'processor' => 'i7-11700',
            'ram' => '16GB',
            'storage' => '512GB SSD',
            'os' => 'Ubuntu 22.04',
            'monitor_brand' => 'Dell',
            'keyboard_brand' => 'Logitech',
            'mouse_brand' => 'Logitech',
        ];

        $createRes = WorkstationRequest::validateCreate($createInput);
        $this->assertTrue($createRes['valid']);
        $this->assertSame('W-01', $createRes['data']['seat_code']);
        $this->assertSame(1, $createRes['data']['lab_id']);
        $this->assertSame('16GB', $createRes['data']['pc_specs']['ram']);

        // Swap validation
        $swapInput = [
            'old_asset_id' => '10',
            'new_asset_id' => '20',
            'removal_reason' => 'Defective power supply',
            'notes' => 'Replaced with spare unit',
        ];
        $swapRes = WorkstationRequest::validateSwap($swapInput);
        $this->assertTrue($swapRes['valid']);
        $this->assertSame(10, $swapRes['data']['old_asset_id']);
        $this->assertSame(20, $swapRes['data']['new_asset_id']);

        // Swap rejecting same asset
        $invalidSwap = [
            'old_asset_id' => '10',
            'new_asset_id' => '10',
            'removal_reason' => '',
        ];
        $invalidSwapRes = WorkstationRequest::validateSwap($invalidSwap);
        $this->assertFalse($invalidSwapRes['valid']);
        $this->assertArrayHasKey('new_asset_id', $invalidSwapRes['errors']);
        $this->assertArrayHasKey('removal_reason', $invalidSwapRes['errors']);
    }

    public function testAssetRequestSanitizationAndValidation(): void
    {
        $validInput = [
            'lab_id' => 'LAB-A',
            'asset_type' => 'Monitor',
            'status' => 'Active',
            'brand_model' => 'Dell P2419H',
            'purchase_cost' => '149.99',
            'specifications' => '{"resolution":"1080p","refresh_rate":"60Hz"}',
            'purchase_date' => '2024-01-15',
        ];

        $res = AssetRequest::validate($validInput, false);
        $this->assertTrue($res['valid']);
        $this->assertSame(149.99, $res['data']['purchase_cost']);
        $this->assertIsArray($res['data']['specifications']);
        $this->assertSame('1080p', $res['data']['specifications']['resolution']);

        $invalidInput = [
            'asset_type' => 'Spaceship',
            'purchase_cost' => '-10',
            'specifications' => 'not json',
        ];
        $badRes = AssetRequest::validate($invalidInput, false);
        $this->assertFalse($badRes['valid']);
        $this->assertArrayHasKey('asset_type', $badRes['errors']);
        $this->assertArrayHasKey('purchase_cost', $badRes['errors']);
        $this->assertArrayHasKey('specifications', $badRes['errors']);
    }

    public function testGrievanceRequestSanitization(): void
    {
        $input = [
            'submitted_by' => 'Jane Doe',
            'stud_enrollment' => 'cs202401',
            'class' => 'TE-CSE',
            'stud_email' => 'JANE.DOE@COLLEGE.EDU',
            'lab_id' => 'LAB-A',
            'device_category' => 'Monitor',
            'grievance_desc' => 'Screen blank and power LED blinking red',
        ];

        $res = GrievanceRequest::validate($input);
        $this->assertTrue($res['valid']);
        $this->assertSame('CS202401', $res['data']['stud_enrollment']);
        $this->assertSame('jane.doe@college.edu', $res['data']['stud_email']);
        $this->assertSame('Monitor', $res['data']['device_category']);

        $statusInput = [
            'status' => 'Resolved',
            'admin_note' => 'Replaced HDMI cable',
        ];
        $statusRes = GrievanceRequest::validateStatus($statusInput);
        $this->assertTrue($statusRes['valid']);
        $this->assertSame('Resolved', $statusRes['data']['status']);
    }
}
