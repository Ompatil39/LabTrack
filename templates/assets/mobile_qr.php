<div class="container py-4" style="max-width: 540px;">
    <!-- Mobile Asset Card -->
    <div class="card shadow border-0 rounded-4 overflow-hidden mb-3">
        <div class="bg-primary text-white p-4 text-center">
            <span class="badge bg-white text-primary mb-2 px-3 py-1 rounded-pill fw-bold text-uppercase" style="letter-spacing: 0.5px;">
                <?= htmlspecialchars((string) ($asset['asset_type'] ?? 'Asset')) ?>
            </span>
            <h1 class="h2 fw-bold mb-1 font-monospace"><?= htmlspecialchars((string) $asset['asset_tag']) ?></h1>
            <p class="mb-0 text-white-50 small"><?= htmlspecialchars((string) ($asset['brand_model'] ?? 'Standard Hardware Unit')) ?></p>
        </div>

        <div class="card-body p-4">
            <!-- Status Badge & Lab Info -->
            <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                <div>
                    <div class="text-muted small">Current Status</div>
                    <?php
                        $status = (string) ($asset['status'] ?? 'Active');
                        $badgeClass = match ($status) {
                            'Active' => 'bg-success',
                            'Spare' => 'bg-info',
                            'Under Repair', 'In Repair' => 'bg-warning text-dark',
                            'Retired' => 'bg-secondary',
                            default => 'bg-primary'
                        };
                    ?>
                    <span class="badge <?= $badgeClass ?> fs-6 px-3 py-2 mt-1 rounded-pill"><?= htmlspecialchars($status) ?></span>
                </div>
                <div class="text-end">
                    <div class="text-muted small">Location</div>
                    <div class="fw-bold"><?= htmlspecialchars((string) ($asset['lab_name'] ?? $asset['lab_id'])) ?></div>
                    <?php if (!empty($asset['seat_code'])): ?>
                        <span class="badge bg-light text-dark border mt-1">Seat: <?= htmlspecialchars((string) $asset['seat_code']) ?></span>
                    <?php else: ?>
                        <span class="text-muted small">Unassigned / Spare</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Asset Metadata Grid -->
            <div class="row g-2 mb-4 small">
                <?php if (!empty($asset['serial_number'])): ?>
                    <div class="col-6">
                        <span class="text-muted d-block">Serial Number:</span>
                        <span class="font-monospace fw-semibold"><?= htmlspecialchars((string) $asset['serial_number']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($asset['warranty_until'])): ?>
                    <div class="col-6">
                        <span class="text-muted d-block">Warranty Until:</span>
                        <span class="fw-semibold"><?= htmlspecialchars((string) $asset['warranty_until']) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Primary Action: 1-Tap Report Issue -->
            <?php
                $reportUrl = '/grievances/submit?' . http_build_query([
                    'lab_id' => $asset['lab_id'] ?? '',
                    'asset_tag' => $asset['asset_tag'] ?? '',
                    'workstation_id' => $asset['workstation_id'] ?? '',
                    'device_category' => $asset['asset_type'] ?? '',
                ]);
            ?>
            <a href="<?= htmlspecialchars($reportUrl) ?>" class="btn btn-danger btn-lg w-100 py-3 rounded-3 shadow-sm d-flex align-items-center justify-content-center gap-2 mb-3">
                <i class="bi bi-exclamation-octagon-fill fs-4"></i>
                <span class="fw-bold">Report an Issue with this Unit</span>
            </a>

            <!-- Quick Actions for Authenticated Staff / Admin -->
            <?php if (!empty($isLoggedIn)): ?>
                <div class="p-3 bg-light rounded-3 border mt-3">
                    <div class="fw-bold small text-muted text-uppercase mb-2">Staff Quick Controls</div>
                    <div class="d-grid gap-2">
                        <a href="/maintenance?asset_id=<?= (int) $asset['asset_id'] ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-tools me-1"></i> Log Maintenance
                        </a>
                        <a href="/assets/<?= urlencode((string) $asset['asset_tag']) ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-gear me-1"></i> Full Asset Profile & Part History
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center mt-3">
                    <a href="/login" class="text-muted small text-decoration-none">
                        <i class="bi bi-lock me-1"></i> Staff Login for Asset Controls
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
