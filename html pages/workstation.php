<?php
require_once __DIR__ . '/../includes/security.php';
requireLogin();

require_once 'db.php';
require_once __DIR__ . '/../includes/WorkstationService.php';
require_once __DIR__ . '/../includes/AssetTagGenerator.php';

use LabTrack\WorkstationService;
use LabTrack\AssetTagGenerator;

$workstationId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($workstationId === 0) {
    http_response_code(400);
    exit('Missing workstation ID.');
}

// Load workstation basics for lab access check
$wsBasic = db_fetch_one($conn, 'SELECT lab_id FROM workstations WHERE workstation_id = ? LIMIT 1', 'i', [$workstationId]);
if ($wsBasic === null) {
    http_response_code(404);
    exit('Workstation not found.');
}

requireLabAccess($conn, $wsBasic['lab_id']);

// --- Handle AJAX POST Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    enforceCsrfProtection(true);

    $action = $_POST['action'] ?? '';

    if ($action === 'swap_part') {
        $oldAssetId = (int) ($_POST['old_asset_id'] ?? 0);
        $newAssetId = (int) ($_POST['new_asset_id'] ?? 0);
        $reason     = trim($_POST['removal_reason'] ?? '');
        $oldStatus  = $_POST['old_asset_status'] ?? 'Faulty';

        if ($oldAssetId === 0 || $newAssetId === 0 || $reason === '') {
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        try {
            WorkstationService::swapWorkstationPart($conn, $workstationId, $oldAssetId, $newAssetId, $reason, $oldStatus);
            echo json_encode(['success' => true, 'message' => 'Part swapped successfully.']);
        } catch (\Throwable $e) {
            app_logger()->error('swap_part_failed', ['error' => $e->getMessage()]);
            echo json_encode(['success' => false, 'message' => 'Swap failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

// --- Load page data ---
$data = WorkstationService::getWorkstationDetails($conn, $workstationId);
if ($data === null) {
    http_response_code(404);
    exit('Workstation not found.');
}

$ws           = $data['workstation'];
$currentParts = $data['current_parts'];
$history      = $data['history'];
$grievances   = $data['grievances'];

// Fetch available spare assets per type (unassigned in this lab)
$sparesByType = [];
foreach ($currentParts as $part) {
    $type = $part['asset_type'];
    if (!isset($sparesByType[$type])) {
        $sparesByType[$type] = db_fetch_all(
            $conn,
            "SELECT a.asset_id, a.asset_tag, a.brand_model, a.status
             FROM assets a
             LEFT JOIN asset_assignments aa ON aa.asset_id = a.asset_id AND aa.removed_at IS NULL
             WHERE a.asset_type = ? AND a.lab_id = ? AND aa.assignment_id IS NULL
             ORDER BY a.asset_tag",
            'ss',
            [$type, $ws['lab_id']]
        );
    }
}

$csrfToken = generateCsrfToken();

function statusBadge(string $status): string {
    $map = [
        'Active'       => 'badge-success',
        'Inactive'     => 'badge-secondary',
        'Under Repair' => 'badge-warning',
        'Faulty'       => 'badge-danger',
        'Retired'      => 'badge-dark',
    ];
    $cls = $map[$status] ?? 'badge-secondary';
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Workstation <?= htmlspecialchars($ws['seat_code'], ENT_QUOTES, 'UTF-8') ?> – <?= htmlspecialchars($ws['lab_id'], ENT_QUOTES, 'UTF-8') ?> | LabTrack</title>
  <meta name="description" content="Detailed view of workstation <?= htmlspecialchars($ws['seat_code'], ENT_QUOTES, 'UTF-8') ?> including installed components, swap history, and grievances." />
  <link rel="icon" type="image/png" href="../public/images/logo.svg" />
  <link rel="stylesheet" href="../public/css/style.css" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
  <style>
    .ws-hero { background: linear-gradient(135deg, #1a1f3c 0%, #2d3561 100%); color: #fff; border-radius: 12px; padding: 2rem; margin-bottom: 2rem; }
    .ws-hero h1 { margin: 0 0 .5rem; font-size: 2rem; }
    .ws-hero .meta { opacity: .75; font-size: .9rem; }
    .parts-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
    .part-card { background: #fff; border-radius: 10px; border: 1px solid #e5e7eb; padding: 1.25rem; box-shadow: 0 2px 8px rgba(0,0,0,.06); transition: transform .2s, box-shadow .2s; }
    .part-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,.1); }
    .part-card .type-icon { font-size: 2rem; margin-bottom: .75rem; }
    .part-card .part-tag { font-family: monospace; font-size: .8rem; background: #f3f4f6; padding: 2px 8px; border-radius: 4px; }
    .part-card .specs-list { font-size: .82rem; color: #6b7280; margin-top: .5rem; }
    .badge { display: inline-block; padding: .3em .7em; border-radius: 9999px; font-size: .75rem; font-weight: 600; }
    .badge-success { background: #d1fae5; color: #065f46; }
    .badge-warning { background: #fef3c7; color: #92400e; }
    .badge-danger  { background: #fee2e2; color: #991b1b; }
    .badge-secondary { background: #f3f4f6; color: #374151; }
    .badge-dark    { background: #1f2937; color: #f9fafb; }
    .section-title { font-size: 1.25rem; font-weight: 700; color: #1f2937; border-left: 4px solid #4f46e5; padding-left: .75rem; margin: 2rem 0 1rem; }
    .swap-btn { background: #4f46e5; color: #fff; border: none; border-radius: 6px; padding: .5rem 1rem; font-size: .85rem; cursor: pointer; margin-top: .75rem; width: 100%; transition: background .2s; }
    .swap-btn:hover { background: #4338ca; }
    table { width: 100%; border-collapse: collapse; font-size: .875rem; }
    th, td { padding: .75rem 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
    th { background: #f9fafb; font-weight: 600; color: #374151; }
    tr:hover td { background: #f9fafb; }
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1000; align-items: center; justify-content: center; }
    .modal-overlay.active { display: flex; }
    .modal-box { background: #fff; border-radius: 12px; padding: 2rem; width: 100%; max-width: 480px; box-shadow: 0 20px 60px rgba(0,0,0,.3); }
    .modal-box h3 { margin: 0 0 1.25rem; font-size: 1.15rem; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-size: .85rem; font-weight: 600; margin-bottom: .35rem; color: #374151; }
    .form-group select, .form-group input, .form-group textarea { width: 100%; padding: .6rem .75rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: .9rem; }
    .btn-row { display: flex; gap: .75rem; margin-top: 1.25rem; }
    .btn-primary { background: #4f46e5; color: #fff; border: none; border-radius: 6px; padding: .65rem 1.5rem; font-size: .9rem; cursor: pointer; }
    .btn-cancel  { background: #f3f4f6; color: #374151; border: none; border-radius: 6px; padding: .65rem 1.5rem; font-size: .9rem; cursor: pointer; }
  </style>
</head>
<body>
<div class="container">
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="logo"><span><i class="fa-brands fa-watchman-monitoring colour"></i>LabTrack</span></div>
    <hr class="solid" />
    <ul class="menu">
      <li class="menu-title">Menu</li>
      <li><a href="index.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
      <li class="active"><a href="labs.php"><i class="fa-solid fa-network-wired"></i> <span>Labs</span></a></li>
      <li><a href="inventory.php"><i class="fa-solid fa-warehouse"></i> Inventory</a></li>
      <li><a href="grievance.php"><i class="fa-solid fa-paper-plane"></i> Grievance</a></li>
    </ul>
    <div class="log-out">
      <a href="logout.php" class="none"><span><i class="fa-solid fa-arrow-right-from-bracket"></i></span> Logout</a>
    </div>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="header">
      <div class="sub-heading"><span>Workstation Detail</span></div>
      <div class="user-info" onclick="window.location.href='profileManage.php';" style="margin-right:.5rem;">
        <i class="fa-solid fa-circle-user"></i>
        <span class="font-rale"><?= htmlspecialchars(strtoupper($_SESSION['username'] ?? 'User'), ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    </div>

    <!-- Hero -->
    <div class="ws-hero">
      <div style="display:flex; align-items:center; gap:1.5rem;">
        <div style="font-size:3.5rem;"><i class="fa-solid fa-desktop"></i></div>
        <div>
          <h1>Seat <?= htmlspecialchars($ws['seat_code'], ENT_QUOTES, 'UTF-8') ?></h1>
          <div class="meta">
            <span><?= htmlspecialchars($ws['lab_id'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php if ($ws['hostname']): ?>
              &nbsp;·&nbsp; <i class="fa-solid fa-server"></i> <?= htmlspecialchars($ws['hostname'], ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
            &nbsp;·&nbsp; <?= statusBadge($ws['status']) ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Currently Installed Components -->
    <h2 class="section-title"><i class="fa-solid fa-microchip"></i> Installed Components</h2>

    <?php if (empty($currentParts)): ?>
      <p style="color:#6b7280;">No components currently installed.</p>
    <?php else: ?>
    <div class="parts-grid">
      <?php foreach ($currentParts as $part): ?>
        <?php
          $iconMap = ['PC'=>'fa-tv','Monitor'=>'fa-desktop','Keyboard'=>'fa-keyboard','Mouse'=>'fa-computer-mouse','CPU'=>'fa-microchip','Printer'=>'fa-print','Switch'=>'fa-network-wired','AccessPoint'=>'fa-wifi','Other'=>'fa-cube'];
          $icon = $iconMap[$part['asset_type']] ?? 'fa-cube';
          $specs = json_decode($part['specs'] ?? '{}', true) ?: [];
          $sparesForType = $sparesByType[$part['asset_type']] ?? [];
        ?>
        <div class="part-card" id="part-card-<?= (int)$part['asset_id'] ?>">
          <div class="type-icon"><i class="fa-solid <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>" style="color:#4f46e5;"></i></div>
          <div style="font-weight:700; font-size:1rem; margin-bottom:.35rem;"><?= htmlspecialchars($part['asset_type'], ENT_QUOTES, 'UTF-8') ?></div>
          <div class="part-tag"><?= htmlspecialchars($part['asset_tag'], ENT_QUOTES, 'UTF-8') ?></div>
          <?php if ($part['brand_model']): ?>
            <div style="margin-top:.5rem; color:#374151; font-size:.9rem;"><?= htmlspecialchars($part['brand_model'], ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>
          <div style="margin-top:.4rem;"><?= statusBadge($part['status']) ?></div>
          <?php if (!empty($specs)): ?>
          <div class="specs-list">
            <?php foreach ($specs as $k => $v): if ($v): ?>
              <div><strong><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $k)), ENT_QUOTES, 'UTF-8') ?>:</strong> <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; endforeach; ?>
          </div>
          <?php endif; ?>
          <button class="swap-btn"
                  data-asset-id="<?= (int)$part['asset_id'] ?>"
                  data-asset-type="<?= htmlspecialchars($part['asset_type'], ENT_QUOTES, 'UTF-8') ?>"
                  data-asset-tag="<?= htmlspecialchars($part['asset_tag'], ENT_QUOTES, 'UTF-8') ?>"
                  onclick="openSwapModal(this)">
            <i class="fa-solid fa-arrows-rotate"></i> Swap / Replace
          </button>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Open Grievances -->
    <?php if (!empty($grievances)): ?>
    <h2 class="section-title" style="border-color:#ef4444;"><i class="fa-solid fa-triangle-exclamation"></i> Open Grievances</h2>
    <table>
      <thead><tr><th>#</th><th>Submitted By</th><th>Description</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach ($grievances as $g): ?>
        <tr>
          <td><a href="viewGrievance.php?id=<?= (int)$g['grievance_id'] ?>">#<?= (int)$g['grievance_id'] ?></a></td>
          <td><?= htmlspecialchars($g['submitted_by'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars(mb_substr($g['grievance_desc'], 0, 100), ENT_QUOTES, 'UTF-8') ?>...</td>
          <td><?= statusBadge($g['status']) ?></td>
          <td><?= htmlspecialchars($g['submission_date'], ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

    <!-- Assignment History -->
    <h2 class="section-title"><i class="fa-solid fa-clock-rotate-left"></i> Component Assignment History</h2>
    <?php if (empty($history)): ?>
      <p style="color:#6b7280;">No assignment history yet.</p>
    <?php else: ?>
    <table>
      <thead><tr><th>Asset Tag</th><th>Type</th><th>Brand/Model</th><th>Installed At</th><th>Removed At</th><th>Removal Reason</th></tr></thead>
      <tbody>
        <?php foreach ($history as $h): ?>
        <tr>
          <td><span class="part-tag"><?= htmlspecialchars($h['asset_tag'], ENT_QUOTES, 'UTF-8') ?></span></td>
          <td><?= htmlspecialchars($h['asset_type'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($h['brand_model'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($h['installed_at'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= $h['removed_at'] ? htmlspecialchars($h['removed_at'], ENT_QUOTES, 'UTF-8') : '<span style="color:#059669;">Active</span>' ?></td>
          <td><?= htmlspecialchars($h['removal_reason'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

  </div><!-- /main-content -->
</div><!-- /container -->

<!-- Swap Modal -->
<div class="modal-overlay" id="swapModal">
  <div class="modal-box">
    <h3><i class="fa-solid fa-arrows-rotate" style="color:#4f46e5;"></i> Swap Component</h3>
    <p style="font-size:.88rem; color:#6b7280; margin-bottom:1rem;">
      Replacing: <strong id="swapOldTag"></strong> (<span id="swapOldType"></span>)
    </p>
    <form id="swapForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
      <input type="hidden" name="action" value="swap_part" />
      <input type="hidden" name="old_asset_id" id="swapOldAssetId" />
      <div class="form-group">
        <label for="swapNewAssetId">Replace With (Spare Asset)</label>
        <select name="new_asset_id" id="swapNewAssetId" required></select>
      </div>
      <div class="form-group">
        <label for="swapReason">Removal Reason *</label>
        <input type="text" name="removal_reason" id="swapReason" required placeholder="e.g., Left click broken, swapped with spare" />
      </div>
      <div class="form-group">
        <label for="swapOldStatus">Old Asset New Status</label>
        <select name="old_asset_status" id="swapOldStatus">
          <option value="Faulty">Faulty</option>
          <option value="Under Repair">Under Repair</option>
          <option value="Inactive">Inactive</option>
          <option value="Retired">Retired</option>
        </select>
      </div>
      <div class="btn-row">
        <button type="submit" class="btn-primary"><i class="fa-solid fa-check"></i> Confirm Swap</button>
        <button type="button" class="btn-cancel" onclick="closeSwapModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
var sparesData = <?= json_encode($sparesByType, JSON_HEX_TAG | JSON_HEX_AMP) ?>;

function openSwapModal(btn) {
  var assetId   = btn.dataset.assetId;
  var assetType = btn.dataset.assetType;
  var assetTag  = btn.dataset.assetTag;

  document.getElementById('swapOldTag').textContent     = assetTag;
  document.getElementById('swapOldType').textContent    = assetType;
  document.getElementById('swapOldAssetId').value       = assetId;
  document.getElementById('swapReason').value           = '';

  var select = document.getElementById('swapNewAssetId');
  select.innerHTML = '';
  var spares = sparesData[assetType] || [];
  if (spares.length === 0) {
    var opt = document.createElement('option');
    opt.value = '';
    opt.textContent = '— No spare assets available —';
    select.appendChild(opt);
  } else {
    spares.forEach(function(s) {
      var opt = document.createElement('option');
      opt.value = s.asset_id;
      opt.textContent = s.asset_tag + (s.brand_model ? ' (' + s.brand_model + ')' : '') + ' [' + s.status + ']';
      select.appendChild(opt);
    });
  }

  document.getElementById('swapModal').classList.add('active');
}

function closeSwapModal() {
  document.getElementById('swapModal').classList.remove('active');
}

document.getElementById('swapForm').addEventListener('submit', function(e) {
  e.preventDefault();
  var formData = new FormData(this);
  fetch('workstation.php?id=<?= (int)$workstationId ?>', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(function(res) {
      if (res.success) {
        closeSwapModal();
        location.reload();
      } else {
        alert('Error: ' + res.message);
      }
    })
    .catch(function() { alert('Network error. Please try again.'); });
});

// Close modal on overlay click
document.getElementById('swapModal').addEventListener('click', function(e) {
  if (e.target === this) closeSwapModal();
});
</script>
</body>
</html>
