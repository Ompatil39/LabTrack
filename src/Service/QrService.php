<?php

declare(strict_types=1);

namespace LabTrack\Service;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class QrService
{
    /**
     * Generate a QR Code as a Data URI (SVG or PNG).
     */
    public function renderDataUri(string $data): string
    {
        $options = new QROptions([
            'version' => QRCode::VERSION_AUTO,
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel' => QRCode::ECC_L,
            'addQuietzone' => true,
        ]);

        return (new QRCode($options))->render($data);
    }

    /**
     * Generate an asset QR payload short URL string (e.g. http://localhost:8000/a/MON-2026-0004).
     */
    public function generatePayload(string $assetTag, string $assetType = '', string $labId = ''): string
    {
        $appUrl = function_exists('app_config') ? (string) (app_config()['app']['url'] ?? '') : '';
        $appUrl = rtrim($appUrl, '/');
        if ($appUrl === '') {
            $appUrl = 'http://localhost:8000';
        }

        return "{$appUrl}/a/{$assetTag}";
    }
}
