<?php
session_start();
if (isset($_SESSION["logged_in"]) !== true) {
    header("Location: login.php");
    exit;
}

include 'db.php';

// Expect lab id
$lab_id = isset($_GET['id']) ? $_GET['id'] : '';
if (empty($lab_id)) {
    http_response_code(400);
    echo "Missing lab id";
    exit;
}

// Load TCPDF via Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Fetch lab
$labSql = "SELECT * FROM labs WHERE lab_id = '" . $conn->real_escape_string($lab_id) . "' LIMIT 1";
$labRes = $conn->query($labSql);
if (!$labRes || $labRes->num_rows === 0) {
    http_response_code(404);
    echo "Lab not found";
    exit;
}
$lab = $labRes->fetch_assoc();

// Stats
$q = function ($sql) use ($conn) {
    $r = $conn->query($sql);
    return ($r && $r->num_rows) ? (int)$r->fetch_assoc()['count'] : 0;
};
$totalPCs = $q("SELECT COUNT(*) as count FROM devices WHERE lab_id='" . $conn->real_escape_string($lab_id) . "' AND device_type='PC'");
$activePCs = $q("SELECT COUNT(*) as count FROM devices WHERE lab_id='" . $conn->real_escape_string($lab_id) . "' AND device_type='PC' AND status='Active'");
$inactivePCs = $totalPCs - $activePCs;

// Fetch PCs
$pcsSql = "SELECT d.device_id, d.device_name, COALESCE(p.pc_id, 'N/A') AS pc_id, d.status
           FROM devices d
           LEFT JOIN pc_details p ON d.device_id = p.device_id
           WHERE d.lab_id='" . $conn->real_escape_string($lab_id) . "' AND d.device_type='PC'
           ORDER BY d.device_id ASC";
$pcsRes = $conn->query($pcsSql);
$pcs = [];
if ($pcsRes) {
    while ($row = $pcsRes->fetch_assoc()) {
        $pcs[] = $row;
    }
}

// PDF Setup - Optimized for label printing
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('LabTrack');
$pdf->SetAuthor('LabTrack');
$pdf->SetTitle('Lab ' . $lab['lab_id'] . ' - PC Barcodes');
$pdf->SetMargins(8, 8, 8);
$pdf->SetAutoPageBreak(true, 8);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Colors
$black = [0, 0, 0];
$darkGray = [52, 58, 64];
$medGray = [108, 117, 125];
$lightGray = [233, 236, 239];
$accent = [13, 110, 253];
$success = [25, 135, 84];
$danger = [220, 53, 69];

// === COVER PAGE ===
$pdf->AddPage();

// Header section with logo
$logoPath = __DIR__ . '/../public/img/logo.png';
$headerH = 35;
$pdf->SetFillColor(248, 249, 250);
$pdf->Rect(8, 8, 194, $headerH, 'F');

if (file_exists($logoPath)) {
    $pdf->Image($logoPath, 12, 12, 16, 16, 'PNG');
}

$pdf->SetXY(32, 12);
$pdf->SetTextColor($darkGray[0], $darkGray[1], $darkGray[2]);
$pdf->SetFont('helvetica', 'B', 22);
$pdf->Cell(0, 8, 'PC Barcode Labels', 0, 1, 'L');

$pdf->SetX(32);
$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor($medGray[0], $medGray[1], $medGray[2]);
$pdf->Cell(0, 5, 'Generated: ' . date('d M Y, H:i'), 0, 1, 'L');

// Lab info card
$pdf->Ln(6);
$cardY = $pdf->GetY();
$pdf->SetFillColor(255, 255, 255);
$pdf->SetDrawColor($lightGray[0], $lightGray[1], $lightGray[2]);
$pdf->SetLineWidth(0.3);
$pdf->RoundedRect(8, $cardY, 194, 42, 2, '1111', 'DF');

$pdf->SetXY(14, $cardY + 6);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetTextColor($darkGray[0], $darkGray[1], $darkGray[2]);
$pdf->Cell(0, 6, $lab['lab_name'], 0, 1, 'L');

$pdf->SetX(14);
$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor($medGray[0], $medGray[1], $medGray[2]);
$pdf->Cell(45, 5, 'Lab Code:', 0, 0, 'L');
$pdf->SetTextColor($darkGray[0], $darkGray[1], $darkGray[2]);
$pdf->Cell(60, 5, $lab['lab_id'], 0, 0, 'L');
$pdf->SetTextColor($medGray[0], $medGray[1], $medGray[2]);
$pdf->Cell(35, 5, 'Department:', 0, 0, 'L');
$pdf->SetTextColor($darkGray[0], $darkGray[1], $darkGray[2]);
$pdf->Cell(0, 5, $lab['department'], 0, 1, 'L');

$pdf->SetX(14);
$pdf->SetTextColor($medGray[0], $medGray[1], $medGray[2]);
$pdf->Cell(45, 5, 'In-Charge:', 0, 0, 'L');
$pdf->SetTextColor($darkGray[0], $darkGray[1], $darkGray[2]);
$pdf->Cell(60, 5, $lab['lab_incharge'], 0, 0, 'L');
$pdf->SetTextColor($medGray[0], $medGray[1], $medGray[2]);
$pdf->Cell(35, 5, 'Location:', 0, 0, 'L');
$pdf->SetTextColor($darkGray[0], $darkGray[1], $darkGray[2]);
$pdf->Cell(0, 5, $lab['building'] . ', Room ' . $lab['room_number'], 0, 1, 'L');

// Stats cards - compact 3-column
$pdf->Ln(8);
$cardW = 62;
$cardH = 28;
$gap = 3;
$startX = 8;
$statsY = $pdf->GetY();

// Total PCs card
$pdf->SetFillColor(248, 249, 250);
$pdf->RoundedRect($startX, $statsY, $cardW, $cardH, 1.5, '1111', 'F');
$pdf->SetXY($startX + 4, $statsY + 5);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor($medGray[0], $medGray[1], $medGray[2]);
$pdf->Cell($cardW - 8, 4, 'TOTAL PCS', 0, 1, 'L');
$pdf->SetX($startX + 4);
$pdf->SetFont('helvetica', 'B', 20);
$pdf->SetTextColor($darkGray[0], $darkGray[1], $darkGray[2]);
$pdf->Cell($cardW - 8, 10, (string)$totalPCs, 0, 0, 'L');

// Active PCs card
$pdf->SetFillColor(217, 243, 228);
$pdf->RoundedRect($startX + $cardW + $gap, $statsY, $cardW, $cardH, 1.5, '1111', 'F');
$pdf->SetXY($startX + $cardW + $gap + 4, $statsY + 5);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor($success[0], $success[1], $success[2]);
$pdf->Cell($cardW - 8, 4, 'ACTIVE', 0, 1, 'L');
$pdf->SetX($startX + $cardW + $gap + 4);
$pdf->SetFont('helvetica', 'B', 20);
$pdf->Cell($cardW - 8, 10, (string)$activePCs, 0, 0, 'L');

// Inactive PCs card
$pdf->SetFillColor(248, 215, 218);
$pdf->RoundedRect($startX + ($cardW + $gap) * 2, $statsY, $cardW, $cardH, 1.5, '1111', 'F');
$pdf->SetXY($startX + ($cardW + $gap) * 2 + 4, $statsY + 5);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor($danger[0], $danger[1], $danger[2]);
$pdf->Cell($cardW - 8, 4, 'INACTIVE', 0, 1, 'L');
$pdf->SetX($startX + ($cardW + $gap) * 2 + 4);
$pdf->SetFont('helvetica', 'B', 20);
$pdf->Cell($cardW - 8, 10, (string)$inactivePCs, 0, 0, 'L');

// Instructions
$pdf->Ln(36);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetTextColor($darkGray[0], $darkGray[1], $darkGray[2]);
$pdf->Cell(0, 6, 'Label Instructions:', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor($medGray[0], $medGray[1], $medGray[2]);
$pdf->MultiCell(0, 4, "• Print on adhesive label sheets (recommended: 70mm x 35mm labels)\n• Each label contains QR code and device information\n• Clean device surface before applying label\n• Scan QR code with any mobile device to view device details\n• Labels are arranged for optimal cutting and application", 0, 'L');

// === LABEL PAGES ===
// Optimal label layout: 3 columns x 7 rows per page (21 labels per page)
// Label size: 63mm wide x 35mm tall (practical for device stickers)
$labelW = 63;
$labelH = 35;
$cols = 3;
$rows = 7;
$marginX = 8;
$marginY = 10;
$gapX = 2.5;
$gapY = 3;

$labelsPerPage = $cols * $rows;
$totalLabels = count($pcs);
$currentLabel = 0;

while ($currentLabel < $totalLabels) {
    $pdf->AddPage();

    // Page title (compact)
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor($medGray[0], $medGray[1], $medGray[2]);
    $pageNum = floor($currentLabel / $labelsPerPage) + 1;
    $totalPages = ceil($totalLabels / $labelsPerPage);
    $pdf->Cell(0, 4, $lab['lab_id'] . ' - Page ' . $pageNum . ' of ' . $totalPages, 0, 1, 'C');
    $pdf->Ln(2);

    for ($row = 0; $row < $rows; $row++) {
        for ($col = 0; $col < $cols; $col++) {
            if ($currentLabel >= $totalLabels) break 2;

            $pc = $pcs[$currentLabel];

            // Calculate position
            $x = $marginX + ($col * ($labelW + $gapX));
            $y = $marginY + 6 + ($row * ($labelH + $gapY));

            // Label background and border
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetDrawColor(200, 200, 200);
            $pdf->SetLineWidth(0.2);
            $pdf->RoundedRect($x, $y, $labelW, $labelH, 1, '1111', 'DF');

            // QR Code (left side, square)
            $qrSize = 28;
            $qrX = $x + 3;
            $qrY = $y + ($labelH - $qrSize) / 2;
            $qrData = json_encode([
                "device_id" => $pc['device_id'],
                "device_name" => $pc['device_name'] ?: "",
                "lab_id" => $lab['lab_id'],
                "timestamp" => time()  // Use current timestamp or fetch from DB if needed
            ]);
            $pdf->write2DBarcode($qrData, 'QRCODE,H', $qrX, $qrY, $qrSize, $qrSize, array('border' => 0), 'N');

            // Text content (right side)
            $textX = $qrX + $qrSize + 3;
            $textW = $labelW - $qrSize - 9;

            // Lab code (small, top)
            $pdf->SetXY($textX, $y + 3);
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor($medGray[0], $medGray[1], $medGray[2]);
            $pdf->Cell($textW, 3, $lab['lab_id'], 0, 1, 'L');

            // Device ID (prominent)
            $pdf->SetX($textX);
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetTextColor($black[0], $black[1], $black[2]);
            $pdf->Cell($textW, 5, $pc['device_id'], 0, 1, 'L');

            // Device name (truncated if needed)
            $pdf->SetX($textX);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor($darkGray[0], $darkGray[1], $darkGray[2]);
            $deviceName = $pc['device_name'];
            if (strlen($deviceName) > 20) {
                $deviceName = substr($deviceName, 0, 17) . '...';
            }
            $pdf->MultiCell($textW, 3.5, $deviceName, 0, 'L', false, 1);

            // Status badge
            $pdf->SetXY($textX, $y + $labelH - 7);
            $statusW = 18;
            if ($pc['status'] === 'Active') {
                $pdf->SetFillColor(217, 243, 228);
                $pdf->SetTextColor($success[0], $success[1], $success[2]);
            } else {
                $pdf->SetFillColor(248, 215, 218);
                $pdf->SetTextColor($danger[0], $danger[1], $danger[2]);
            }
            $pdf->SetFont('helvetica', 'B', 6);
            $pdf->RoundedRect($textX, $y + $labelH - 7, $statusW, 4, 0.5, '1111', 'F');
            $pdf->SetXY($textX, $y + $labelH - 7);
            $pdf->Cell($statusW, 4, strtoupper($pc['status']), 0, 0, 'C');

            $currentLabel++;
        }
    }
}

// Output (robust streaming)
if (function_exists('ob_get_level')) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}
$safeLab = preg_replace('/[^A-Za-z0-9_-]+/', '_', $lab['lab_id']);
$filename = 'Lab_' . $safeLab . '_Barcode_Labels.pdf';
$content = $pdf->Output('', 'S');
header('Content-Type: application/pdf');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . strlen($content));
echo $content;
exit;
