-- LabTrack Phase 6: Monitoring Agent & Telemetry
-- Migration 004: Agent Credentials, Nonces, Telemetry Samples, Rollups, Snapshots, and Drift Alerts

-- 1. Agent Credentials
CREATE TABLE IF NOT EXISTS agent_credentials (
    credential_id INT AUTO_INCREMENT PRIMARY KEY,
    workstation_id INT NOT NULL UNIQUE,
    enrollment_token_hash CHAR(64) NULL,
    enrollment_expires_at DATETIME NULL,
    api_key_id VARCHAR(36) NULL UNIQUE,
    signing_secret_encrypted VARCHAR(255) NULL,
    agent_version VARCHAR(20) NULL,
    enrolled_at DATETIME NULL,
    last_seen_at DATETIME NULL,
    last_ip VARCHAR(45) NULL,
    is_revoked TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_agent_cred_workstation FOREIGN KEY (workstation_id)
        REFERENCES workstations(workstation_id) ON DELETE CASCADE,
    INDEX idx_agent_cred_token (enrollment_token_hash),
    INDEX idx_agent_cred_key (api_key_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Agent Nonces (Replay Attack Prevention)
CREATE TABLE IF NOT EXISTS agent_nonces (
    nonce CHAR(32) NOT NULL PRIMARY KEY,
    api_key_id VARCHAR(36) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_agent_nonces_created (created_at),
    INDEX idx_agent_nonces_key_created (api_key_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Live Telemetry & Status Columns on Workstations
ALTER TABLE workstations
    ADD COLUMN mac_address VARCHAR(17) NULL UNIQUE AFTER seat_code,
    ADD COLUMN ip_address VARCHAR(45) NULL AFTER mac_address,
    ADD COLUMN os_info VARCHAR(120) NULL AFTER ip_address,
    ADD COLUMN last_seen_at DATETIME NULL AFTER os_info,
    ADD COLUMN logged_in_user VARCHAR(100) NULL AFTER last_seen_at,
    ADD COLUMN uptime_seconds BIGINT NULL AFTER logged_in_user,
    ADD COLUMN latest_cpu_pct DECIMAL(5,2) NULL AFTER uptime_seconds,
    ADD COLUMN latest_ram_pct DECIMAL(5,2) NULL AFTER latest_cpu_pct,
    ADD COLUMN latest_disk_pct DECIMAL(5,2) NULL AFTER latest_ram_pct,
    ADD COLUMN cpu_temp_c DECIMAL(5,2) NULL AFTER latest_disk_pct,
    ADD COLUMN smart_health VARCHAR(20) NOT NULL DEFAULT 'UNKNOWN' AFTER cpu_temp_c,
    ADD COLUMN has_hardware_drift TINYINT(1) NOT NULL DEFAULT 0 AFTER smart_health;

-- 4. Telemetry Samples (Raw Telemetry Time-Series)
CREATE TABLE IF NOT EXISTS telemetry_samples (
    sample_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    workstation_id INT NOT NULL,
    sampled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cpu_pct DECIMAL(5,2) NOT NULL,
    ram_pct DECIMAL(5,2) NOT NULL,
    disk_pct DECIMAL(5,2) NOT NULL,
    cpu_temp_c DECIMAL(5,2) NULL,
    uptime_seconds BIGINT NULL,
    logged_in_user VARCHAR(100) NULL,
    smart_health VARCHAR(20) NOT NULL DEFAULT 'UNKNOWN',
    CONSTRAINT fk_ts_workstation FOREIGN KEY (workstation_id)
        REFERENCES workstations(workstation_id) ON DELETE CASCADE,
    INDEX idx_ts_ws_sampled (workstation_id, sampled_at),
    INDEX idx_ts_sampled (sampled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Telemetry Hourly Rollups (1-Year Aggregated Metrics)
CREATE TABLE IF NOT EXISTS telemetry_hourly_rollups (
    rollup_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    workstation_id INT NOT NULL,
    bucket_hour DATETIME NOT NULL,
    avg_cpu_pct DECIMAL(5,2) NOT NULL,
    max_cpu_pct DECIMAL(5,2) NOT NULL,
    avg_ram_pct DECIMAL(5,2) NOT NULL,
    max_ram_pct DECIMAL(5,2) NOT NULL,
    avg_disk_pct DECIMAL(5,2) NOT NULL,
    max_temp_c DECIMAL(5,2) NULL,
    sample_count INT NOT NULL,
    CONSTRAINT fk_rollup_workstation FOREIGN KEY (workstation_id)
        REFERENCES workstations(workstation_id) ON DELETE CASCADE,
    UNIQUE KEY uq_rollup_ws_hour (workstation_id, bucket_hour),
    INDEX idx_rollup_hour (bucket_hour)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Workstation Inventory Snapshots
CREATE TABLE IF NOT EXISTS workstation_inventory_snapshots (
    snapshot_id INT AUTO_INCREMENT PRIMARY KEY,
    workstation_id INT NOT NULL UNIQUE,
    reported_hardware JSON NOT NULL,
    installed_software JSON NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_snapshot_workstation FOREIGN KEY (workstation_id)
        REFERENCES workstations(workstation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Hardware Drift Alerts
CREATE TABLE IF NOT EXISTS hardware_drift_alerts (
    drift_id INT AUTO_INCREMENT PRIMARY KEY,
    workstation_id INT NOT NULL,
    asset_id INT NULL,
    field_name VARCHAR(60) NOT NULL,
    expected_value VARCHAR(190) NOT NULL,
    reported_value VARCHAR(190) NOT NULL,
    status ENUM('Open','Acknowledged','Resolved') NOT NULL DEFAULT 'Open',
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    CONSTRAINT fk_drift_workstation FOREIGN KEY (workstation_id)
        REFERENCES workstations(workstation_id) ON DELETE CASCADE,
    CONSTRAINT fk_drift_asset FOREIGN KEY (asset_id)
        REFERENCES assets(asset_id) ON DELETE SET NULL,
    INDEX idx_drift_ws_status (workstation_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
