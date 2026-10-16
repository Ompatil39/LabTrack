<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Warranty Alerts & Depreciation Report</h1>
            <p class="text-muted small mb-0">Track upcoming warranty expirations and calculate asset book values with straight-line depreciation.</p>
        </div>
        <div>
            <a href="/maintenance" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Maintenance
            </a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-primary">
                <div class="card-body">
                    <div class="text-muted small text-uppercase fw-bold">Total Initial Cost</div>
                    <div class="h4 mb-0 fw-bold">₹<?= number_format((float) ($reports['depreciation']['total_cost'] ?? 0.0), 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-warning">
                <div class="card-body">
                    <div class="text-muted small text-uppercase fw-bold">Total Depreciation</div>
                    <div class="h4 mb-0 fw-bold">₹<?= number_format((float) ($reports['depreciation']['total_depreciation'] ?? 0.0), 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-success">
                <div class="card-body">
                    <div class="text-muted small text-uppercase fw-bold">Current Book Value</div>
                    <div class="h4 mb-0 fw-bold">₹<?= number_format((float) ($reports['depreciation']['total_book_value'] ?? 0.0), 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-danger">
                <div class="card-body">
                    <div class="text-muted small text-uppercase fw-bold">Total Maintenance Spent</div>
                    <div class="h4 mb-0 fw-bold">₹<?= number_format((float) ($reports['aggregate_stats']['total_cost'] ?? 0.0), 2) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Warranty Expiration Alerts -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-danger"><i class="bi bi-shield-exclamation me-1"></i> Warranty Expiration Alerts (Within 90 Days)</h6>
        </div>
        <div class="card-body p-0">
            <?php
                $expired = $reports['warranty_alerts']['expired'] ?? [];
                $w30 = $reports['warranty_alerts']['within_30'] ?? [];
                $w60 = $reports['warranty_alerts']['within_60'] ?? [];
                $w90 = $reports['warranty_alerts']['within_90'] ?? [];
                $allAlerts = array_merge($expired, $w30, $w60, $w90);
            ?>
            <?php if (empty($allAlerts)): ?>
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-shield-check fs-2 text-success d-block mb-2"></i>
                    No assets with warranty expiring in the next 90 days.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Status</th>
                                <th>Asset Tag</th>
                                <th>Type / Model</th>
                                <th>Lab / Seat</th>
                                <th>Warranty Expiry</th>
                                <th>Days Remaining</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allAlerts as $a): ?>
                                <?php
                                    $days = (int) $a['days_left'];
                                    $urgencyBadge = $days < 0
                                        ? 'bg-danger'
                                        : ($days <= 30 ? 'bg-warning text-dark' : 'bg-info');
                                    $urgencyText = $days < 0 ? 'Expired (' . abs($days) . 'd ago)' : "Expires in {$days}d";
                                ?>
                                <tr>
                                    <td><span class="badge <?= $urgencyBadge ?>"><?= $urgencyText ?></span></td>
                                    <td class="fw-bold font-monospace"><?= htmlspecialchars((string) $a['asset_tag']) ?></td>
                                    <td><?= htmlspecialchars((string) ($a['brand_model'] ?: $a['asset_type'])) ?></td>
                                    <td>
                                        <div><?= htmlspecialchars((string) ($a['lab_name'] ?? $a['lab_id'])) ?></div>
                                        <?php if (!empty($a['seat_code'])): ?>
                                            <span class="badge bg-light text-dark border">Seat <?= htmlspecialchars((string) $a['seat_code']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars((string) $a['warranty_until']) ?></td>
                                    <td><?= $days ?> days</td>
                                    <td>
                                        <a href="/assets/<?= urlencode((string) $a['asset_tag']) ?>" class="btn btn-outline-primary btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Depreciation Report Table -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-calculator me-1"></i> Straight-Line Asset Depreciation Schedule</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Asset Tag</th>
                            <th>Lab</th>
                            <th>Purchase Date</th>
                            <th>Age</th>
                            <th>Useful Life</th>
                            <th>Purchase Cost</th>
                            <th>Salvage Value</th>
                            <th>Annual Dep.</th>
                            <th>Accum. Dep.</th>
                            <th>Current Book Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $depAssets = $reports['depreciation']['assets'] ?? []; ?>
                        <?php if (empty($depAssets)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">No assets available for depreciation report.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($depAssets as $da): ?>
                                <tr>
                                    <td class="fw-bold font-monospace">
                                        <a href="/assets/<?= urlencode((string) $da['asset_tag']) ?>" class="text-decoration-none">
                                            <?= htmlspecialchars((string) $da['asset_tag']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars((string) ($da['lab_name'] ?? $da['lab_id'])) ?></td>
                                    <td><?= htmlspecialchars((string) ($da['purchase_date'] ?? '—')) ?></td>
                                    <td><?= (float) ($da['age_years'] ?? 0.0) ?> yrs</td>
                                    <td><?= (int) ($da['useful_life_years'] ?? 5) ?> yrs</td>
                                    <td class="fw-semibold">₹<?= number_format((float) ($da['purchase_cost'] ?? 0.0), 2) ?></td>
                                    <td>₹<?= number_format((float) ($da['salvage_value'] ?? 0.0), 2) ?></td>
                                    <td>₹<?= number_format((float) ($da['annual_depreciation'] ?? 0.0), 2) ?>/yr</td>
                                    <td class="text-danger">₹<?= number_format((float) ($da['accumulated_depreciation'] ?? 0.0), 2) ?></td>
                                    <td class="fw-bold text-success">₹<?= number_format((float) ($da['book_value'] ?? 0.0), 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
