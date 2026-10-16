-- Migration: 003_phase5_core_upgrades.sql
-- Description: Core Feature Upgrades (Audit Logs, SLA Tickets, Comments, Status History, Notification Queue, Maintenance Records, Asset Lifecycle)

-- 1. Audit Logs (5.1)
CREATE TABLE IF NOT EXISTS audit_logs (
    audit_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_uid INT NULL,
    username VARCHAR(100) NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id VARCHAR(60) NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_user (user_uid),
    INDEX idx_audit_action (action),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. SLA Policies (5.2)
CREATE TABLE IF NOT EXISTS sla_policies (
    priority ENUM('Low', 'Medium', 'High', 'Critical') PRIMARY KEY,
    response_hours INT NOT NULL,
    resolve_hours INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO sla_policies (priority, response_hours, resolve_hours) VALUES
('Critical', 1, 4),
('High', 4, 24),
('Medium', 8, 48),
('Low', 24, 120)
ON DUPLICATE KEY UPDATE response_hours = VALUES(response_hours), resolve_hours = VALUES(resolve_hours);

-- 3. Upgrade grievances table for SLA and tracking
ALTER TABLE grievances
    ADD COLUMN tracking_code VARCHAR(24) NULL UNIQUE AFTER grievance_id,
    ADD COLUMN priority ENUM('Low', 'Medium', 'High', 'Critical') NOT NULL DEFAULT 'Medium' AFTER tracking_code,
    ADD COLUMN assigned_to_uid INT NULL AFTER priority,
    ADD COLUMN escalated_to_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER assigned_to_uid,
    ADD COLUMN first_responded_at DATETIME NULL AFTER admin_note,
    ADD COLUMN resolved_at DATETIME NULL AFTER first_responded_at,
    ADD COLUMN sla_response_due_at DATETIME NULL AFTER resolved_at,
    ADD COLUMN sla_resolve_due_at DATETIME NULL AFTER sla_response_due_at;

-- Create tickets view alias
CREATE OR REPLACE VIEW tickets AS SELECT * FROM grievances;

-- 4. Ticket Comments
CREATE TABLE IF NOT EXISTS ticket_comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    grievance_id INT NOT NULL,
    author_uid INT NULL,
    author_name VARCHAR(100) NOT NULL,
    is_internal TINYINT(1) DEFAULT 0,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tc_grievance (grievance_id),
    INDEX idx_tc_author (author_uid),
    INDEX idx_tc_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Ticket Status History
CREATE TABLE IF NOT EXISTS ticket_status_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    grievance_id INT NOT NULL,
    old_status VARCHAR(30) NULL,
    new_status VARCHAR(30) NOT NULL,
    changed_by_uid INT NULL,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tsh_grievance (grievance_id),
    INDEX idx_tsh_changed_by (changed_by_uid),
    INDEX idx_tsh_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Notification Queue Table
CREATE TABLE IF NOT EXISTS notification_jobs (
    job_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    channel VARCHAR(20) DEFAULT 'email',
    recipient VARCHAR(190) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    payload JSON NOT NULL,
    status ENUM('pending', 'processing', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    available_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_error TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nj_status_avail (status, available_at),
    INDEX idx_nj_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Maintenance Records (5.3)
CREATE TABLE IF NOT EXISTS maintenance_records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    workstation_id INT NULL,
    lab_id VARCHAR(20) NOT NULL,
    maintenance_type ENUM('Repair', 'Preventive', 'Inspection', 'Upgrade') NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    vendor VARCHAR(120) NULL,
    cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    downtime_hours DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    next_scheduled_date DATE NULL,
    performed_by_uid INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mr_asset (asset_id),
    INDEX idx_mr_workstation (workstation_id),
    INDEX idx_mr_lab (lab_id),
    INDEX idx_mr_type (maintenance_type),
    INDEX idx_mr_next_date (next_scheduled_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Add Lifecycle Columns to Assets
ALTER TABLE assets
    ADD COLUMN salvage_value DECIMAL(10,2) DEFAULT 0.00,
    ADD COLUMN useful_life_years INT DEFAULT 5,
    ADD COLUMN retired_at DATETIME NULL,
    ADD COLUMN retire_reason VARCHAR(255) NULL;
