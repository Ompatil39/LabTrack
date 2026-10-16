export interface DriftAlert {
  drift_id: number;
  workstation_id: number;
  asset_id: number | null;
  field_name: string;
  expected_value: string;
  reported_value: string;
  status: 'Open' | 'Acknowledged' | 'Resolved';
  detected_at?: string;
  resolved_at?: string | null;
}

export interface TelemetrySample {
  sample_id: number;
  workstation_id: number;
  sampled_at: string;
  cpu_pct: number;
  ram_pct: number;
  disk_pct: number;
  cpu_temp_c: number | null;
  uptime_seconds: number | null;
  logged_in_user: string | null;
  smart_health: string;
}

export interface TelemetryResponse {
  success: boolean;
  timestamp: string;
  data: {
    workstation_id: number;
    seat_code: string | null;
    lab_id: string | null;
    status: 'Online' | 'Stale' | 'Offline';
    last_seen_at: string | null;
    logged_in_user: string | null;
    uptime_seconds: number | null;
    ip_address: string | null;
    mac_address: string | null;
    latest_cpu_pct: number | null;
    latest_ram_pct: number | null;
    latest_disk_pct: number | null;
    cpu_temp_c: number | null;
    smart_health: string;
    has_hardware_drift: boolean;
    samples: TelemetrySample[];
    drift_alerts: DriftAlert[];
  };
  error: {
    message: string;
    details?: unknown;
  } | null;
}

function updateTelemetryUi(data: TelemetryResponse['data']): void {
  // 1. Status badge
  const statusBadge = document.getElementById('telemetry-status-badge');
  if (statusBadge) {
    statusBadge.textContent = data.status;
    statusBadge.className = 'status-badge ' + (
      data.status === 'Online' ? 'status-active' :
      data.status === 'Stale' ? 'status-in-repair' : 'status-inactive'
    );
  }

  // 2. Last seen & logged-in user
  const lastSeenEl = document.getElementById('telemetry-last-seen');
  if (lastSeenEl) {
    lastSeenEl.textContent = data.last_seen_at || 'Never';
  }

  const userEl = document.getElementById('telemetry-logged-user');
  if (userEl) {
    userEl.textContent = data.logged_in_user || '—';
  }

  // 3. Progress bars
  const cpuPct = data.latest_cpu_pct ?? 0;
  const cpuBar = document.getElementById('telemetry-cpu-bar');
  const cpuText = document.getElementById('telemetry-cpu-text');
  if (cpuBar) cpuBar.style.width = Math.min(100, Math.max(0, cpuPct)) + '%';
  if (cpuText) cpuText.textContent = cpuPct.toFixed(1) + '%';

  const ramPct = data.latest_ram_pct ?? 0;
  const ramBar = document.getElementById('telemetry-ram-bar');
  const ramText = document.getElementById('telemetry-ram-text');
  if (ramBar) ramBar.style.width = Math.min(100, Math.max(0, ramPct)) + '%';
  if (ramText) ramText.textContent = ramPct.toFixed(1) + '%';

  const diskPct = data.latest_disk_pct ?? 0;
  const diskBar = document.getElementById('telemetry-disk-bar');
  const diskText = document.getElementById('telemetry-disk-text');
  if (diskBar) diskBar.style.width = Math.min(100, Math.max(0, diskPct)) + '%';
  if (diskText) diskText.textContent = diskPct.toFixed(1) + '%';

  // 4. CPU Temp
  const tempEl = document.getElementById('telemetry-temp');
  if (tempEl) {
    tempEl.textContent = data.cpu_temp_c !== null ? data.cpu_temp_c.toFixed(1) + '°C' : 'N/A';
  }

  // 5. SMART Health
  const smartBadge = document.getElementById('telemetry-smart-badge');
  if (smartBadge) {
    smartBadge.textContent = data.smart_health;
    const s = data.smart_health.toUpperCase();
    smartBadge.className = 'status-badge ' + (
      s === 'HEALTHY' ? 'status-active' :
      s === 'WARNING' ? 'status-in-repair' : 'status-inactive'
    );
  }

  // 6. Drift Warning Banner
  const driftBanner = document.getElementById('telemetry-drift-banner');
  if (driftBanner) {
    driftBanner.style.display = data.has_hardware_drift ? 'block' : 'none';
  }
}

function initTelemetryPolling(): void {
  const card = document.getElementById('telemetry-card');
  if (!card) {
    return;
  }

  const workstationId = parseInt(card.getAttribute('data-workstation-id') || '0', 10);
  if (!workstationId || isNaN(workstationId)) {
    return;
  }

  let timerId: number | null = null;

  async function poll(): Promise<void> {
    try {
      const res = await fetch(`/api/workstations/${workstationId}/telemetry`, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      if (res.status === 401 || res.status === 403) {
        if (timerId !== null) {
          window.clearInterval(timerId);
          timerId = null;
        }
        return;
      }

      if (res.ok) {
        const json: TelemetryResponse = await res.json();
        if (json.success && json.data) {
          updateTelemetryUi(json.data);
        }
      }
    } catch {
      // Network hiccup; keep polling next tick
    }
  }

  timerId = window.setInterval(poll, 15000);
}

if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTelemetryPolling);
  } else {
    initTelemetryPolling();
  }
}
