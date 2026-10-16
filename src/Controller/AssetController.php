<?php

declare(strict_types=1);

namespace LabTrack\Controller;

use InvalidArgumentException;
use LabTrack\Http\Request;
use LabTrack\Http\Response;
use LabTrack\Repository\LabRepository;
use LabTrack\Service\AssetService;
use LabTrack\Service\QrService;
use LabTrack\Validation\AssetRequest;
use LabTrack\View\View;
use TCPDF;

class AssetController
{
    private AssetService $assetService;
    private LabRepository $labRepository;

    public function __construct(
        ?AssetService $assetService = null,
        ?LabRepository $labRepository = null
    ) {
        $this->assetService = $assetService ?? new AssetService();
        $this->labRepository = $labRepository ?? new LabRepository();
    }

    public function index(Request $request): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }

        $isAdmin = function_exists('isAdmin') ? isAdmin() : false;
        $userUid = function_exists('currentUserUid') ? currentUserUid() : null;
        $username = function_exists('currentUsername') ? currentUsername() : '';
        $fullName = (string) ($_SESSION['full_name'] ?? '');

        $filters = [
            'lab_id' => $request->query('lab_id'),
            'asset_type' => $request->query('asset_type'),
            'status' => $request->query('status'),
            'search' => $request->query('search'),
        ];
        $page = (int) $request->query('page', 1);

        try {
            $labs = $this->labRepository->findAll($userUid, $isAdmin, $username, $fullName);
            $result = $this->assetService->listAssets(
                $filters,
                $page,
                25,
                $userUid,
                $isAdmin,
                $username,
                $fullName
            );
        } catch (\Throwable $e) {
            $labs = [];
            $result = ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 25, 'total_pages' => 1];
        }

        $html = View::render('assets/index', [
            'title' => 'Hardware & Asset Inventory',
            'assets' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'totalPages' => $result['total_pages'],
            'filters' => $filters,
            'labs' => $labs,
            'isAdmin' => $isAdmin,
            'flash' => $this->getAndClearFlash(),
        ]);

        return Response::html($html);
    }

    public function show(Request $request, string $tag): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }

        $isAdmin = function_exists('isAdmin') ? isAdmin() : false;
        $userUid = function_exists('currentUserUid') ? currentUserUid() : null;
        $username = function_exists('currentUsername') ? currentUsername() : '';
        $fullName = (string) ($_SESSION['full_name'] ?? '');

        try {
            $asset = $this->assetService->getAssetByTag($tag, $userUid, $isAdmin, $username, $fullName);
        } catch (\Throwable $e) {
            return new Response('403 Forbidden: ' . View::e($e->getMessage()), 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        if ($asset === null) {
            return new Response("404 Not Found: Asset '{$tag}' not found.", 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $html = View::render('assets/show', [
            'title' => 'Asset ' . $tag,
            'asset' => $asset,
            'isAdmin' => $isAdmin,
            'flash' => $this->getAndClearFlash(),
        ]);

        return Response::html($html);
    }

    public function create(Request $request): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }
        if (function_exists('requireRole')) {
            requireRole('Admin');
        }

        $isAdmin = function_exists('isAdmin') ? isAdmin() : true;
        $userUid = function_exists('currentUserUid') ? currentUserUid() : null;
        $username = function_exists('currentUsername') ? currentUsername() : '';
        $fullName = (string) ($_SESSION['full_name'] ?? '');

        $labs = $this->labRepository->findAll($userUid, $isAdmin, $username, $fullName);
        $selectedLab = (string) $request->query('lab_id', '');

        $html = View::render('assets/create', [
            'title' => 'Register Physical Asset',
            'labs' => $labs,
            'selectedLab' => $selectedLab,
            'errors' => [],
            'old' => ['lab_id' => $selectedLab],
        ]);

        return Response::html($html);
    }

    public function store(Request $request): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }
        if (function_exists('requireRole')) {
            requireRole('Admin');
        }

        if (!$request->validateCsrf()) {
            return new Response('403 Forbidden: Invalid or missing CSRF token.', 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $validation = AssetRequest::validate($request->post(), false);
        if (!$validation['valid']) {
            $labs = $this->labRepository->findAll(null, true);
            $html = View::render('assets/create', [
                'title' => 'Register Physical Asset',
                'labs' => $labs,
                'selectedLab' => $request->post('lab_id', ''),
                'errors' => $validation['errors'],
                'old' => $request->post(),
            ]);

            return new Response($html, 422, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        try {
            $tag = $this->assetService->registerAsset($validation['data']);
            $this->setFlash('success', "Asset registered successfully with tag '{$tag}'.");
            return Response::redirect('/assets/' . urlencode($tag));
        } catch (InvalidArgumentException $e) {
            $labs = $this->labRepository->findAll(null, true);
            $html = View::render('assets/create', [
                'title' => 'Register Physical Asset',
                'labs' => $labs,
                'selectedLab' => $request->post('lab_id', ''),
                'errors' => ['general' => $e->getMessage()],
                'old' => $request->post(),
            ]);

            return new Response($html, 422, ['Content-Type' => 'text/html; charset=UTF-8']);
        } catch (\Throwable $e) {
            return new Response('Internal error creating asset: ' . View::e($e->getMessage()), 500);
        }
    }

    public function edit(Request $request, string $tag): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }
        if (function_exists('requireRole')) {
            requireRole('Admin');
        }

        $asset = $this->assetService->getAssetByTag($tag, null, true, 'admin');
        if ($asset === null) {
            return new Response("404 Not Found: Asset '{$tag}' not found.", 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $labs = $this->labRepository->findAll(null, true);

        $html = View::render('assets/edit', [
            'title' => 'Edit Asset ' . $tag,
            'asset' => $asset,
            'labs' => $labs,
            'errors' => [],
            'old' => $asset,
        ]);

        return Response::html($html);
    }

    public function update(Request $request, string $tag): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }
        if (function_exists('requireRole')) {
            requireRole('Admin');
        }

        if (!$request->validateCsrf()) {
            return new Response('403 Forbidden: Invalid or missing CSRF token.', 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $asset = $this->assetService->getAssetByTag($tag, null, true, 'admin');
        if ($asset === null) {
            return new Response("404 Not Found: Asset '{$tag}' not found.", 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $validation = AssetRequest::validate($request->post(), true);
        if (!$validation['valid']) {
            $labs = $this->labRepository->findAll(null, true);
            $html = View::render('assets/edit', [
                'title' => 'Edit Asset ' . $tag,
                'asset' => array_merge($asset, $request->post()),
                'labs' => $labs,
                'errors' => $validation['errors'],
                'old' => $request->post(),
            ]);

            return new Response($html, 422, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        try {
            $this->assetService->updateAsset((int) $asset['asset_id'], $validation['data']);
            $this->setFlash('success', "Asset '{$tag}' updated successfully.");
            return Response::redirect('/assets/' . urlencode($tag));
        } catch (\Throwable $e) {
            return new Response('Internal error updating asset: ' . View::e($e->getMessage()), 500);
        }
    }

    public function destroy(Request $request, string $tag): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }
        if (function_exists('requireRole')) {
            requireRole('Admin');
        }

        if (!$request->validateCsrf()) {
            return new Response('403 Forbidden: Invalid or missing CSRF token.', 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $asset = $this->assetService->getAssetByTag($tag, null, true, 'admin');
        if ($asset === null) {
            return new Response("404 Not Found: Asset '{$tag}' not found.", 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        try {
            $this->assetService->deleteAsset((int) $asset['asset_id']);
            $this->setFlash('success', "Asset '{$tag}' deleted successfully.");
            return Response::redirect('/inventory');
        } catch (\Throwable $e) {
            return new Response('Internal error deleting asset: ' . View::e($e->getMessage()), 500);
        }
    }

    /**
     * POST /assets/{tag}/retire — Retire / Dispose workflow.
     */
    public function retire(Request $request, string $tag): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }
        if (function_exists('requireRole')) {
            requireRole('Admin');
        }

        if (!$request->validateCsrf()) {
            return new Response('403 Forbidden: Invalid or missing CSRF token.', 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $isAdmin = function_exists('isAdmin') ? isAdmin() : false;
        $userUid = function_exists('currentUserUid') ? currentUserUid() : null;
        $username = function_exists('currentUsername') ? currentUsername() : '';
        $fullName = (string) ($_SESSION['full_name'] ?? '');

        $reason = trim((string) $request->post('reason', 'Asset Retired / End of Life'));
        if ($reason === '') {
            $reason = 'Asset Retired / End of Life';
        }

        try {
            $this->assetService->retireAsset($tag, $reason, $userUid, $isAdmin, $username, $fullName);
            if ($request->wantsJson()) {
                return Response::json(['success' => true, 'message' => "Asset '{$tag}' retired successfully."]);
            }
            $this->setFlash('success', "Asset '{$tag}' has been retired and active assignments closed.");
            return Response::redirect('/assets/' . urlencode($tag));
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return Response::json(['success' => false, 'error' => $e->getMessage()], 400);
            }
            $this->setFlash('error', 'Unable to retire asset: ' . View::e($e->getMessage()));
            return Response::redirect('/assets/' . urlencode($tag));
        }
    }

    /**
     * GET /a/{tag} — Mobile PWA Landing Page for QR scanning.
     */
    public function mobileLanding(Request $request, string $tag): Response
    {
        $asset = $this->assetService->getAssetByTag($tag, null, true, 'guest');
        if ($asset === null) {
            return new Response("404 Not Found: Asset '{$tag}' not found.", 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $reportUrl = '/grievances/submit?lab_id=' . urlencode((string) ($asset['lab_id'] ?? ''))
            . '&asset_tag=' . urlencode((string) ($asset['asset_tag'] ?? ''))
            . '&workstation_id=' . urlencode((string) ($asset['workstation_id'] ?? ''));

        if ($request->wantsJson()) {
            return Response::json([
                'success' => true,
                'data' => [
                    'asset' => $asset,
                    'report_url' => $reportUrl,
                ],
            ]);
        }

        $isLoggedIn = session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['logged_in']);
        $isAdmin = function_exists('isAdmin') ? isAdmin() : false;

        $html = View::render('assets/mobile_qr', [
            'title' => 'Asset ' . $tag,
            'asset' => $asset,
            'isLoggedIn' => $isLoggedIn,
            'isAdmin' => $isAdmin,
            'flash' => $this->getAndClearFlash(),
        ], 'layout/base');

        return Response::html($html);
    }

    /**
     * JSON filter API endpoint - replaces legacy filterDevices.php with authenticated guard.
     */
    public function filter(Request $request): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin(true);
        }

        $isAdmin = function_exists('isAdmin') ? isAdmin() : false;
        $userUid = function_exists('currentUserUid') ? currentUserUid() : null;
        $username = function_exists('currentUsername') ? currentUsername() : '';
        $fullName = (string) ($_SESSION['full_name'] ?? '');

        $filters = [
            'lab_id' => $request->query('lab_id'),
            'asset_type' => $request->query('asset_type') ?? $request->query('device_type'),
            'status' => $request->query('status'),
            'search' => $request->query('search'),
        ];

        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 50);

        $result = $this->assetService->listAssets(
            array_filter($filters),
            $page,
            $perPage,
            $userUid,
            $isAdmin,
            $username,
            $fullName
        );

        return Response::json([
            'success' => true,
            'data' => $result['items'],
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'total_pages' => $result['total_pages'],
            ],
        ]);
    }

    /**
     * JSON select endpoint - replaces legacy get_devices.php with authenticated guard.
     */
    public function selectOptions(Request $request): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin(true);
        }

        $isAdmin = function_exists('isAdmin') ? isAdmin() : false;
        $userUid = function_exists('currentUserUid') ? currentUserUid() : null;
        $username = function_exists('currentUsername') ? currentUsername() : '';
        $fullName = (string) ($_SESSION['full_name'] ?? '');

        $labId = (string) $request->query('lab_id', '');
        $type = (string) ($request->query('asset_type') ?? $request->query('type', ''));

        $filters = array_filter(['lab_id' => $labId, 'asset_type' => $type, 'status' => 'Active']);
        $result = $this->assetService->listAssets($filters, 1, 100, $userUid, $isAdmin, $username, $fullName);

        $options = array_map(function ($item) {
            return [
                'id' => $item['asset_id'],
                'tag' => $item['asset_tag'],
                'label' => $item['asset_tag'] . ' — ' . ($item['brand_model'] ?: $item['asset_type']) . ($item['seat_code'] ? " (Seat: {$item['seat_code']})" : ' (Spare)'),
                'type' => $item['asset_type'],
                'seat_code' => $item['seat_code'],
            ];
        }, $result['items']);

        return Response::json(['success' => true, 'data' => $options]);
    }

    public function apiSearch(Request $request): Response
    {
        return $this->filter($request);
    }

    public function apiSelect(Request $request): Response
    {
        return $this->selectOptions($request);
    }

    /**
     * POST /assets/export-pdf or GET /assets/export-pdf
     * Exports asset inventory to PDF using TCPDF.
     */
    public function exportPdf(Request $request): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }

        $isAdmin = function_exists('isAdmin') ? isAdmin() : false;
        $userUid = function_exists('currentUserUid') ? currentUserUid() : null;
        $username = function_exists('currentUsername') ? currentUsername() : '';
        $fullName = (string) ($_SESSION['full_name'] ?? '');

        $labId = $request->query('lab_id', $request->post('lab_id', ''));
        $filters = [];
        if ($labId !== '') {
            $filters['lab_id'] = $labId;
        }

        $data = $this->assetService->listAssets($filters, 1, 500, $userUid, $isAdmin, $username, $fullName);
        $assets = $data['items'];

        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('LabTrack');
        $pdf->SetAuthor('LabTrack System');
        $pdf->SetTitle('LabTrack Asset Inventory');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'LabTrack — Hardware Assets Inventory Report', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 6, 'Generated: ' . date('Y-m-d H:i:s') . ' | Total Records: ' . count($assets), 0, 1, 'L');
        $pdf->Ln(4);

        $htmlTable = '<table border="1" cellpadding="4" cellspacing="0" style="font-size: 8pt; border-collapse: collapse;">
            <thead>
                <tr style="background-color: #f1f5f9; font-weight: bold;">
                    <th width="15%">Asset Tag</th>
                    <th width="15%">Type</th>
                    <th width="25%">Brand / Model</th>
                    <th width="15%">Serial Number</th>
                    <th width="15%">Lab ID</th>
                    <th width="15%">Status</th>
                </tr>
            </thead>
            <tbody>';

        if (empty($assets)) {
            $htmlTable .= '<tr><td colspan="6" align="center">No assets found matching the criteria.</td></tr>';
        } else {
            foreach ($assets as $a) {
                $tag = htmlspecialchars((string) ($a['asset_tag'] ?? ''), ENT_QUOTES, 'UTF-8');
                $type = htmlspecialchars((string) ($a['asset_type'] ?? ''), ENT_QUOTES, 'UTF-8');
                $brand = htmlspecialchars((string) ($a['brand_model'] ?? '—'), ENT_QUOTES, 'UTF-8');
                $serial = htmlspecialchars((string) ($a['serial_number'] ?? '—'), ENT_QUOTES, 'UTF-8');
                $lab = htmlspecialchars((string) ($a['lab_id'] ?? ''), ENT_QUOTES, 'UTF-8');
                $status = htmlspecialchars((string) ($a['status'] ?? ''), ENT_QUOTES, 'UTF-8');

                $htmlTable .= "<tr>
                    <td>{$tag}</td>
                    <td>{$type}</td>
                    <td>{$brand}</td>
                    <td>{$serial}</td>
                    <td>{$lab}</td>
                    <td>{$status}</td>
                </tr>";
            }
        }
        $htmlTable .= '</tbody></table>';

        $pdf->writeHTML($htmlTable, true, false, false, false, '');
        $pdfOutput = $pdf->Output('labtrack_assets.pdf', 'S');

        return new Response($pdfOutput, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="labtrack_assets_' . date('Ymd_His') . '.pdf"',
        ]);
    }

    /**
     * GET /assets/{tag}/qr
     * Returns an SVG QR code for the given asset tag.
     */
    public function qr(Request $request, string $tag): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }

        $isAdmin = function_exists('isAdmin') ? isAdmin() : false;
        $userUid = function_exists('currentUserUid') ? currentUserUid() : null;
        $username = function_exists('currentUsername') ? currentUsername() : '';
        $fullName = (string) ($_SESSION['full_name'] ?? '');

        try {
            $asset = $this->assetService->getAssetByTag($tag, $userUid, $isAdmin, $username, $fullName);
        } catch (\Throwable $e) {
            return new Response('403 Forbidden: ' . View::e($e->getMessage()), 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        if (!$asset) {
            return new Response("404 Not Found: Asset '{$tag}' does not exist.", 404, ['Content-Type' => 'text/plain']);
        }

        $qrService = new QrService();
        $payload = $qrService->generatePayload(
            $asset['asset_tag'],
            $asset['asset_type'],
            $asset['lab_id']
        );
        $svgData = $qrService->renderDataUri($payload);

        return new Response($svgData, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
        ]);
    }

    private function setFlash(string $type, string $message): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['flash_message'] = [
                'type' => $type,
                'message' => $message,
            ];
        }
    }

    private function getAndClearFlash(): ?array
    {
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['flash_message'])) {
            $flash = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            return is_array($flash) ? $flash : null;
        }
        return null;
    }
}
