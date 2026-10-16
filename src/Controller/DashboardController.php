<?php

declare(strict_types=1);

namespace LabTrack\Controller;

use LabTrack\Http\Request;
use LabTrack\Http\Response;
use LabTrack\Repository\AssetRepository;
use LabTrack\Repository\GrievanceRepository;
use LabTrack\Repository\LabRepository;
use LabTrack\Repository\MaintenanceRepository;
use LabTrack\Support\SimpleCache;
use LabTrack\View\View;

class DashboardController
{
    private LabRepository $labRepo;
    private AssetRepository $assetRepo;
    private GrievanceRepository $grievanceRepo;
    private MaintenanceRepository $maintenanceRepo;
    private SimpleCache $cache;

    public function __construct(
        ?LabRepository $labRepo = null,
        ?AssetRepository $assetRepo = null,
        ?GrievanceRepository $grievanceRepo = null,
        ?MaintenanceRepository $maintenanceRepo = null,
        ?SimpleCache $cache = null
    ) {
        $this->labRepo = $labRepo ?? new LabRepository();
        $this->assetRepo = $assetRepo ?? new AssetRepository();
        $this->grievanceRepo = $grievanceRepo ?? new GrievanceRepository();
        $this->maintenanceRepo = $maintenanceRepo ?? new MaintenanceRepository();
        $this->cache = $cache ?? new SimpleCache();
    }

    /**
     * GET / and GET /dashboard — requires login.
     * Accessible by Admin and Incharge (data scoped to incharge's labs).
     */
    public function index(Request $request): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }

        $isAdmin = function_exists('isAdmin') ? isAdmin() : false;
        $userUid = function_exists('currentUserUid') ? currentUserUid() : null;
        $username = function_exists('currentUsername') ? currentUsername() : '';
        $fullName = (string) ($_SESSION['full_name'] ?? '');

        $cacheKey = 'dashboard_analytics_' . ($isAdmin ? 'admin' : ('u_' . ($userUid ?? 0)));

        /** @var array<string, mixed> $stats */
        $stats = $this->cache->remember($cacheKey, 60, function () use ($userUid, $isAdmin, $username, $fullName) {
            $labSummary = [
                'total_labs' => 0,
                'active_labs' => 0,
                'total_capacity' => 0,
            ];
            try {
                $labSummary = $this->labRepo->summary($userUid, $isAdmin, $username, $fullName);
            } catch (\Throwable) {
            }

            $assetStatusCounts = [];
            $assetTypeCounts = [];
            $totalAssets = 0;
            try {
                $assetStatusCounts = $this->assetRepo->countByStatus($userUid, $isAdmin, $username, $fullName);
                $assetTypeCounts = $this->assetRepo->countByType($userUid, $isAdmin, $username, $fullName);
                $totalAssets = $this->assetRepo->countAll([], $userUid, $isAdmin, $username, $fullName);
            } catch (\Throwable) {
            }

            $grievanceStatusCounts = [];
            $recentGrievances = [];
            $analyticsSummary = [
                'mttr_hours' => 0.0,
                'tickets_per_lab' => [],
                'sla_breach_count' => 0,
            ];
            try {
                $grievanceStatusCounts = $this->grievanceRepo->countByStatus($userUid, $isAdmin, $username, $fullName);
                $recentGrievances = $this->grievanceRepo->recentOpen(10, $userUid, $isAdmin, $username, $fullName);
                $analyticsSummary = $this->grievanceRepo->getAnalyticsSummary($userUid, $isAdmin, $username, $fullName);
            } catch (\Throwable) {
            }

            $maintenanceStats = [
                'total_cost' => 0.0,
                'total_downtime_hours' => 0.0,
                'by_type' => [],
                'by_lab' => [],
            ];
            $expiringWarrantiesCount = 0;
            try {
                $maintenanceStats = $this->maintenanceRepo->getAggregateStats();
                $alerts = $this->maintenanceRepo->getWarrantyAlerts(30);
                $expiringWarrantiesCount = count($alerts);
            } catch (\Throwable) {
            }

            $openGrievances = ($grievanceStatusCounts['Submitted'] ?? 0)
                + ($grievanceStatusCounts['In Progress'] ?? 0)
                + ($grievanceStatusCounts['Under Review'] ?? 0);

            $operationalAssets = $assetStatusCounts['Operational'] ?? ($assetStatusCounts['Active'] ?? 0);
            $underRepairAssets = ($assetStatusCounts['Under Repair'] ?? 0) + ($assetStatusCounts['In Repair'] ?? 0);

            return [
                'labSummary' => $labSummary,
                'totalAssets' => $totalAssets,
                'operationalAssets' => $operationalAssets,
                'underRepairAssets' => $underRepairAssets,
                'assetStatusCounts' => $assetStatusCounts,
                'assetTypeCounts' => $assetTypeCounts,
                'openGrievances' => $openGrievances,
                'grievanceStatusCounts' => $grievanceStatusCounts,
                'recentGrievances' => $recentGrievances,
                'analyticsSummary' => $analyticsSummary,
                'maintenanceStats' => $maintenanceStats,
                'expiringWarrantiesCount' => $expiringWarrantiesCount,
            ];
        });

        $html = View::render('dashboard/index', array_merge($stats, [
            'title' => $isAdmin ? 'Administrator Dashboard' : 'Lab Incharge Dashboard',
            'isAdmin' => $isAdmin,
            'username' => $username,
            'fullName' => $fullName,
        ]));

        return Response::html($html);
    }

    /**
     * GET /admin — requires Admin role.
     * Strict admin entrance. Incharges are blocked with 403 Forbidden.
     */
    public function admin(Request $request): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }

        if (function_exists('requireRole')) {
            requireRole('Admin');
        }

        return $this->index($request);
    }
}
