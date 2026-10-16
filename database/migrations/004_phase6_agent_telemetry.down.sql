-- LabTrack Phase 6: Monitoring Agent & Telemetry Revert
-- Rollback Migration 004

-- Drop tables in reverse dependency order
DROP TABLE IF EXISTS hardware_drift_alerts;
DROP TABLE IF EXISTS workstation_inventory_snapshots;
DROP TABLE IF EXISTS telemetry_hourly_rollups;
DROP TABLE IF EXISTS telemetry_samples;
DROP TABLE IF EXISTS agent_nonces;
DROP TABLE IF EXISTS agent_credentials;

-- Remove added telemetry columns from workstations
ALTER TABLE workstations
    DROP COLUMN IF EXISTS mac_address,
    DROP COLUMN IF EXISTS ip_address,
    DROP COLUMN IF EXISTS os_info,
    DROP COLUMN IF EXISTS last_seen_at,
    DROP COLUMN IF EXISTS logged_in_user,
    DROP COLUMN IF EXISTS uptime_seconds,
    DROP COLUMN IF EXISTS latest_cpu_pct,
    DROP COLUMN IF EXISTS latest_ram_pct,
    DROP COLUMN IF EXISTS latest_disk_pct,
    DROP COLUMN IF EXISTS cpu_temp_c,
    DROP COLUMN IF EXISTS smart_health,
    DROP COLUMN IF EXISTS has_hardware_drift;
