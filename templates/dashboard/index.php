<?php
/** @var string $title */
/** @var bool $isAdmin */
/** @var string $username */
/** @var string $fullName */
/** @var array $labSummary */
/** @var int $totalAssets */
/** @var int $operationalAssets */
/** @var int $underRepairAssets */
/** @var array $assetStatusCounts */
/** @var array $assetTypeCounts */
/** @var int $openGrievances */
/** @var array $grievanceStatusCounts */
/** @var array $recentGrievances */
/** @var array $analyticsSummary */
/** @var array $maintenanceStats */
/** @var int $expiringWarrantiesCount */

use LabTrack\View\View;

$chartTypeLabels = array_keys($assetTypeCounts);
$chartTypeValues = array_values($assetTypeCounts);

$chartStatusLabels = array_keys($assetStatusCounts);
$chartStatusValues = array_values($assetStatusCounts);
?>

<div style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.85rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;">
                <?= $isAdmin ? 'Institutional Operations Dashboard' : 'Laboratory Management Dashboard' ?>
            </h1>
            <p style="color: var(--muted); font-size: 0.95rem;">
                Welcome back, <strong><?= View::e($fullName ?: $username) ?></strong> &bull;
                <?= $isAdmin ? 'System-wide overview and real-time lab diagnostics.' : 'Diagnostics and status for your assigned computer laboratories.' ?>
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <?php if ($isAdmin): ?>
                <a href="/labs/create" class="btn btn-outline" style="font-size: 0.875rem;">
                    <i class="fas fa-plus"></i> Add Lab
                </a>
                <a href="/workstations/create" class="btn btn-outline" style="font-size: 0.875rem;">
                    <i class="fas fa-desktop"></i> Add Workstation
                </a>
                <a href="/assets/create" class="btn btn-outline" style="font-size: 0.875rem;">
                    <i class="fas fa-barcode"></i> Add Asset
                </a>
            <?php endif; ?>
            <a href="/grievances/submit" target="_blank" class="btn btn-primary" style="font-size: 0.875rem;">
                <i class="fas fa-paper-plane"></i> Public Grievance Portal
            </a>
        </div>
    </div>
</div>

<!-- KPI Metric Cards Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
    <!-- Labs Card -->
    <div class="card" style="border-left: 4px solid var(--primary); padding: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <span style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--muted); letter-spacing: 0.05em;">
                    <?= $isAdmin ? 'Total Laboratories' : 'Assigned Labs' ?>
                </span>
                <div style="font-size: 2rem; font-weight: 700; color: #0f172a; margin: 0.35rem 0;">
                    <?= (int) ($labSummary['total_labs'] ?? 0) ?>
                </div>
                <div style="font-size: 0.825rem; color: var(--muted);">
                    <strong><?= (int) ($labSummary['active_labs'] ?? 0) ?></strong> active &bull;
                    <strong><?= (int) ($labSummary['total_capacity'] ?? 0) ?></strong> total capacity
                </div>
            </div>
            <div style="background: #eff6ff; color: var(--primary); padding: 0.75rem; border-radius: 8px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
            </div>
        </div>
    </div>

    <!-- Assets Card -->
    <div class="card" style="border-left: 4px solid var(--success); padding: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <span style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--muted); letter-spacing: 0.05em;">
                    Hardware Assets
                </span>
                <div style="font-size: 2rem; font-weight: 700; color: #0f172a; margin: 0.35rem 0;">
                    <?= (int) $totalAssets ?>
                </div>
                <div style="font-size: 0.825rem; color: var(--muted);">
                    <span style="color: var(--success); font-weight: 600;">✓ <?= (int) $operationalAssets ?></span> operational
                </div>
            </div>
            <div style="background: #f0fdf4; color: var(--success); padding: 0.75rem; border-radius: 8px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
            </div>
        </div>
    </div>

    <!-- Maintenance / Faulty Card -->
    <div class="card" style="border-left: 4px solid var(--warning); padding: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <span style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--muted); letter-spacing: 0.05em;">
                    Under Repair / Attention
                </span>
                <div style="font-size: 2rem; font-weight: 700; color: #0f172a; margin: 0.35rem 0;">
                    <?= (int) $underRepairAssets ?>
                </div>
                <div style="font-size: 0.825rem; color: var(--muted);">
                    Requires hardware maintenance
                </div>
            </div>
            <div style="background: #fffbeb; color: var(--warning); padding: 0.75rem; border-radius: 8px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
            </div>
        </div>
    </div>

    <!-- Grievances Card -->
    <div class="card" style="border-left: 4px solid var(--danger); padding: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <span style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--muted); letter-spacing: 0.05em;">
                    Open Grievances
                </span>
                <div style="font-size: 2rem; font-weight: 700; color: #0f172a; margin: 0.35rem 0;">
                    <?= (int) $openGrievances ?>
                </div>
                <div style="font-size: 0.825rem; color: var(--muted);">
                    <?= (int) ($grievanceStatusCounts['Submitted'] ?? 0) ?> new &bull;
                    <?= (int) ($grievanceStatusCounts['In Progress'] ?? 0) ?> in progress
                </div>
            </div>
            <div style="background: #fef2f2; color: var(--danger); padding: 0.75rem; border-radius: 8px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
            </div>
        </div>
    </div>
</div>

<!-- Operational SLA & Maintenance Analytics Grid (Phase 5.5) -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
    <!-- MTTR Card -->
    <div class="card" style="border-left: 4px solid #8b5cf6; padding: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <span style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--muted); letter-spacing: 0.05em;">
                    Mean Time To Resolve (MTTR)
                </span>
                <div style="font-size: 2rem; font-weight: 700; color: #0f172a; margin: 0.35rem 0;">
                    <?= round((float) ($analyticsSummary['mttr_hours'] ?? 0), 1) ?><span style="font-size: 1rem; font-weight: 500; color: var(--muted);"> hrs</span>
                </div>
                <div style="font-size: 0.825rem; color: var(--muted);">
                    Average ticket resolution turnaround
                </div>
            </div>
            <div style="background: #f5f3ff; color: #8b5cf6; padding: 0.75rem; border-radius: 8px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            </div>
        </div>
    </div>

    <!-- SLA Breach Card -->
    <div class="card" style="border-left: 4px solid <?= ((int) ($analyticsSummary['sla_breach_count'] ?? 0)) > 0 ? '#ef4444' : '#10b981' ?>; padding: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <span style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--muted); letter-spacing: 0.05em;">
                    SLA Breaches
                </span>
                <div style="font-size: 2rem; font-weight: 700; color: <?= ((int) ($analyticsSummary['sla_breach_count'] ?? 0)) > 0 ? '#ef4444' : '#0f172a' ?>; margin: 0.35rem 0;">
                    <?= (int) ($analyticsSummary['sla_breach_count'] ?? 0) ?>
                </div>
                <div style="font-size: 0.825rem; color: var(--muted);">
                    <?= ((int) ($analyticsSummary['sla_breach_count'] ?? 0)) > 0 ? '<strong style="color: #ef4444;">Action Required</strong> &bull; Escalated to Admin' : 'All active tickets within SLA' ?>
                </div>
            </div>
            <div style="background: <?= ((int) ($analyticsSummary['sla_breach_count'] ?? 0)) > 0 ? '#fef2f2' : '#f0fdf4' ?>; color: <?= ((int) ($analyticsSummary['sla_breach_count'] ?? 0)) > 0 ? '#ef4444' : '#10b981' ?>; padding: 0.75rem; border-radius: 8px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"></polygon><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            </div>
        </div>
    </div>

    <!-- Maintenance Cost & Downtime Card -->
    <div class="card" style="border-left: 4px solid #0284c7; padding: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <span style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--muted); letter-spacing: 0.05em;">
                    Maintenance &amp; Downtime
                </span>
                <div style="font-size: 2rem; font-weight: 700; color: #0f172a; margin: 0.35rem 0;">
                    $<?= number_format((float) ($maintenanceStats['total_cost'] ?? 0), 2) ?>
                </div>
                <div style="font-size: 0.825rem; color: var(--muted);">
                    <strong><?= round((float) ($maintenanceStats['total_downtime_hours'] ?? 0), 1) ?> hrs</strong> total downtime logged
                </div>
            </div>
            <div style="background: #f0f9ff; color: #0284c7; padding: 0.75rem; border-radius: 8px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            </div>
        </div>
    </div>

    <!-- Warranty Expirations Card -->
    <div class="card" style="border-left: 4px solid #f59e0b; padding: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <span style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--muted); letter-spacing: 0.05em;">
                    Expiring Warranties
                </span>
                <div style="font-size: 2rem; font-weight: 700; color: #0f172a; margin: 0.35rem 0;">
                    <?= (int) ($expiringWarrantiesCount ?? 0) ?>
                </div>
                <div style="font-size: 0.825rem; color: var(--muted);">
                    <a href="/maintenance/reports" style="color: #d97706; text-decoration: underline;">Within 30 days &rarr;</a>
                </div>
            </div>
            <div style="background: #fffbeb; color: #f59e0b; padding: 0.75rem; border-radius: 8px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            </div>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Asset Distribution Chart Card -->
    <div class="card">
        <h2 style="font-size: 1.1rem; font-weight: 600; color: #0f172a; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between;">
            <span>Hardware Distribution by Type</span>
            <small style="font-size: 0.8rem; font-weight: 400; color: var(--muted);">Category Breakdown</small>
        </h2>
        <div style="position: relative; height: 260px; display: flex; justify-content: center; align-items: center;">
            <canvas id="assetTypeChart"></canvas>
        </div>
    </div>

    <!-- Status Breakdown Chart Card -->
    <div class="card">
        <h2 style="font-size: 1.1rem; font-weight: 600; color: #0f172a; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between;">
            <span>Asset Status &amp; Operational Health</span>
            <small style="font-size: 0.8rem; font-weight: 400; color: var(--muted);">Health Metrics</small>
        </h2>
        <div style="position: relative; height: 260px; display: flex; justify-content: center; align-items: center;">
            <canvas id="assetStatusChart"></canvas>
        </div>
    </div>
</div>

<!-- Recent Grievances Section -->
<div class="card" style="padding: 0; overflow: hidden; margin-bottom: 2rem;">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="font-size: 1.15rem; font-weight: 600; color: #0f172a; margin-bottom: 0.2rem;">
                Recent Open Grievances
            </h2>
            <p style="font-size: 0.85rem; color: var(--muted); margin: 0;">
                Latest unresolved issues reported across laboratories.
            </p>
        </div>
        <a href="/grievances" class="btn btn-outline" style="font-size: 0.85rem;">
            View All Grievances &rarr;
        </a>
    </div>

    <div style="overflow-x: auto;">
        <table class="table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="padding: 0.75rem 1.25rem; text-align: left; font-size: 0.8rem; color: var(--muted); text-transform: uppercase;">ID</th>
                    <th style="padding: 0.75rem 1.25rem; text-align: left; font-size: 0.8rem; color: var(--muted); text-transform: uppercase;">Student / Submitter</th>
                    <th style="padding: 0.75rem 1.25rem; text-align: left; font-size: 0.8rem; color: var(--muted); text-transform: uppercase;">Lab</th>
                    <th style="padding: 0.75rem 1.25rem; text-align: left; font-size: 0.8rem; color: var(--muted); text-transform: uppercase;">Status</th>
                    <th style="padding: 0.75rem 1.25rem; text-align: left; font-size: 0.8rem; color: var(--muted); text-transform: uppercase;">Issue Description</th>
                    <th style="padding: 0.75rem 1.25rem; text-align: left; font-size: 0.8rem; color: var(--muted); text-transform: uppercase;">Reported At</th>
                    <th style="padding: 0.75rem 1.25rem; text-align: right; font-size: 0.8rem; color: var(--muted); text-transform: uppercase;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentGrievances)): ?>
                    <tr>
                        <td colspan="7" style="padding: 2.5rem; text-align: center; color: var(--muted);">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 0.5rem; color: var(--success);"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                            <p style="margin: 0; font-weight: 500;">No open grievances found!</p>
                            <small>All reported laboratory equipment is operating normally.</small>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentGrievances as $grv): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 0.85rem 1.25rem; font-weight: 600; color: var(--primary);">
                                #<?= (int) $grv['grievance_id'] ?>
                            </td>
                            <td style="padding: 0.85rem 1.25rem;">
                                <strong><?= View::e($grv['submitted_by']) ?></strong>
                            </td>
                            <td style="padding: 0.85rem 1.25rem;">
                                <?= View::e($grv['lab_name'] ?? $grv['lab_id']) ?>
                            </td>
                            <td style="padding: 0.85rem 1.25rem;">
                                <?php
                                $badgeColor = match ($grv['status']) {
                                    'Submitted'    => 'background: #fffbeb; color: #b45309;',
                                    'In Progress'  => 'background: #eff6ff; color: #1d4ed8;',
                                    'Under Review' => 'background: #f5f3ff; color: #6d28d9;',
                                    'Resolved'     => 'background: #f0fdf4; color: #15803d;',
                                    default        => 'background: #f1f5f9; color: #475569;',
                                };
                                ?>
                                <span style="display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; <?= $badgeColor ?>">
                                    <?= View::e($grv['status']) ?>
                                </span>
                            </td>
                            <td style="padding: 0.85rem 1.25rem; max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #334155;">
                                <?= View::e($grv['grievance_desc']) ?>
                            </td>
                            <td style="padding: 0.85rem 1.25rem; font-size: 0.825rem; color: var(--muted);">
                                <?= View::e(date('M d, Y H:i', strtotime($grv['submission_date']))) ?>
                            </td>
                            <td style="padding: 0.85rem 1.25rem; text-align: right;">
                                <a href="/grievances/<?= (int) $grv['grievance_id'] ?>" class="btn btn-outline" style="padding: 0.25rem 0.6rem; font-size: 0.8rem;">
                                    Review &rarr;
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Breakdown Sections Grid (Tickets per Lab & Maintenance by Lab) -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Tickets Per Lab Card -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #0f172a; margin: 0;">
                    Tickets by Laboratory
                </h3>
                <small style="color: var(--muted); font-size: 0.8rem;">Grievance volume across facilities</small>
            </div>
            <a href="/grievances" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.25rem 0.6rem;">All Tickets</a>
        </div>
        <div style="padding: 1rem 1.5rem;">
            <?php
            $ticketsPerLab = $analyticsSummary['tickets_per_lab'] ?? [];
            if (empty($ticketsPerLab)):
            ?>
                <p style="color: var(--muted); font-size: 0.9rem; text-align: center; margin: 1rem 0;">No laboratory tickets recorded yet.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                    <?php foreach ($ticketsPerLab as $tpl): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span style="font-weight: 600; font-size: 0.9rem; color: #1e293b;"><?= View::e((string) ($tpl['lab_id'] ?? 'Unknown')) ?></span>
                            </div>
                            <span class="badge" style="background: #e2e8f0; color: #334155; font-size: 0.8rem; font-weight: 600;">
                                <?= (int) ($tpl['ticket_count'] ?? 0) ?> tickets
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Maintenance by Lab Card -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #0f172a; margin: 0;">
                    Maintenance Breakdown by Lab
                </h3>
                <small style="color: var(--muted); font-size: 0.8rem;">Servicing costs and accumulated downtime</small>
            </div>
            <a href="/maintenance" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.25rem 0.6rem;">Maintenance Log</a>
        </div>
        <div style="padding: 1rem 1.5rem;">
            <?php
            $maintByLab = $maintenanceStats['by_lab'] ?? [];
            if (empty($maintByLab)):
            ?>
                <p style="color: var(--muted); font-size: 0.9rem; text-align: center; margin: 1rem 0;">No maintenance events logged yet.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                    <?php foreach ($maintByLab as $mbl): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <span style="font-weight: 600; font-size: 0.9rem; color: #1e293b;"><?= View::e((string) ($mbl['lab_id'] ?? 'Unknown')) ?></span>
                                <div style="font-size: 0.75rem; color: var(--muted);">
                                    <?= (int) ($mbl['maintenance_count'] ?? 0) ?> events &bull; <?= round((float) ($mbl['total_downtime'] ?? 0), 1) ?> hrs downtime
                                </div>
                            </div>
                            <span style="font-weight: 700; color: #0f172a; font-size: 0.9rem;">
                                $<?= number_format((float) ($mbl['total_cost'] ?? 0), 2) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="/public/js/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const typeLabels = <?= json_encode($chartTypeLabels) ?>;
    const typeValues = <?= json_encode($chartTypeValues) ?>;

    const statusLabels = <?= json_encode($chartStatusLabels) ?>;
    const statusValues = <?= json_encode($chartStatusValues) ?>;

    // Palette
    const colors = [
        '#2563eb', '#10b981', '#f59e0b', '#ef4444', 
        '#8b5cf6', '#06b6d4', '#ec4899', '#64748b'
    ];

    // Chart 1: Distribution by Type
    const ctxType = document.getElementById('assetTypeChart');
    if (ctxType && typeof Chart !== 'undefined') {
        new Chart(ctxType, {
            type: 'doughnut',
            data: {
                labels: typeLabels.length ? typeLabels : ['No Assets Recorded'],
                datasets: [{
                    data: typeValues.length ? typeValues : [1],
                    backgroundColor: typeValues.length ? colors.slice(0, typeLabels.length) : ['#e2e8f0'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    }
                }
            }
        });
    }

    // Chart 2: Status Breakdown
    const ctxStatus = document.getElementById('assetStatusChart');
    if (ctxStatus && typeof Chart !== 'undefined') {
        new Chart(ctxStatus, {
            type: 'bar',
            data: {
                labels: statusLabels.length ? statusLabels : ['No Data'],
                datasets: [{
                    label: 'Asset Count',
                    data: statusValues.length ? statusValues : [0],
                    backgroundColor: [
                        '#10b981', '#f59e0b', '#ef4444', '#64748b', '#3b82f6'
                    ],
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, precision: 0 }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
});
</script>
