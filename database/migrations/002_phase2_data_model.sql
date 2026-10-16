-- Phase 2: Data Model (Workstations, Assets, Assignment History, Relational Integrity)
-- Migration 002 - UP

-- ============================================================
-- 1. Fix users table: ensure integer user_id for FK support
--    (existing user_id is VARCHAR(20) PK - add int surrogate key)
-- ============================================================
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS uid INT NULL AUTO_INCREMENT UNIQUE;

-- ============================================================
-- 2. workstations table
-- ============================================================
CREATE TABLE IF NOT EXISTS workstations (
  workstation_id  INT AUTO_INCREMENT PRIMARY KEY,
  lab_id          VARCHAR(20) NOT NULL,
  seat_code       VARCHAR(20) NOT NULL,
  hostname        VARCHAR(100),
  status          ENUM('Active','Inactive','Under Repair') NOT NULL DEFAULT 'Active',
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_lab_seat (lab_id, seat_code),
  INDEX idx_ws_lab_status (lab_id, status),
  CONSTRAINT fk_ws_lab FOREIGN KEY (lab_id) REFERENCES labs(lab_id) ON UPDATE CASCADE ON DELETE RESTRICT
);

-- ============================================================
-- 3. assets table (unified physical items)
-- ============================================================
CREATE TABLE IF NOT EXISTS assets (
  asset_id        INT AUTO_INCREMENT PRIMARY KEY,
  asset_tag       VARCHAR(30) NOT NULL UNIQUE,
  asset_type      ENUM('PC','Monitor','Keyboard','Mouse','CPU','Printer','Switch','AccessPoint','Other') NOT NULL,
  lab_id          VARCHAR(20) NOT NULL,
  brand_model     VARCHAR(100),
  serial_number   VARCHAR(100) UNIQUE,
  status          ENUM('Active','Inactive','Under Repair','Faulty','Retired') NOT NULL DEFAULT 'Active',
  purchase_date   DATE,
  warranty_until  DATE,
  purchase_cost   DECIMAL(10,2),
  specs           JSON,
  qr_payload      VARCHAR(255),
  legacy_device_id VARCHAR(50) NULL UNIQUE,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_assets_lab FOREIGN KEY (lab_id) REFERENCES labs(lab_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_assets_lab (lab_id),
  INDEX idx_assets_type (asset_type),
  INDEX idx_assets_status (status),
  INDEX idx_assets_created (created_at)
);

-- ============================================================
-- 4. asset_tag_sequences (atomic sequential tag generation)
-- ============================================================
CREATE TABLE IF NOT EXISTS asset_tag_sequences (
  prefix      VARCHAR(10) NOT NULL,
  seq_year    INT NOT NULL,
  last_val    INT NOT NULL DEFAULT 0,
  PRIMARY KEY (prefix, seq_year)
);

-- ============================================================
-- 5. asset_assignments (with generated column for uniqueness)
-- ============================================================
CREATE TABLE IF NOT EXISTS asset_assignments (
  assignment_id   INT AUTO_INCREMENT PRIMARY KEY,
  asset_id        INT NOT NULL,
  workstation_id  INT NOT NULL,
  parent_asset_id INT NULL,
  installed_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  removed_at      DATETIME NULL,
  removal_reason  VARCHAR(255),
  active_asset_id INT GENERATED ALWAYS AS (IF(removed_at IS NULL, asset_id, NULL)) STORED,
  CONSTRAINT fk_aa_asset FOREIGN KEY (asset_id) REFERENCES assets(asset_id) ON DELETE CASCADE,
  CONSTRAINT fk_aa_workstation FOREIGN KEY (workstation_id) REFERENCES workstations(workstation_id) ON DELETE CASCADE,
  CONSTRAINT fk_aa_parent_asset FOREIGN KEY (parent_asset_id) REFERENCES assets(asset_id) ON DELETE SET NULL,
  UNIQUE KEY uq_single_active_assignment (active_asset_id),
  INDEX idx_aa_workstation_active (workstation_id, removed_at),
  INDEX idx_aa_asset_history (asset_id, installed_at)
);

-- ============================================================
-- 6. lab_incharges many-to-many (relational fix)
-- ============================================================
CREATE TABLE IF NOT EXISTS lab_incharges (
  lab_id      VARCHAR(20) NOT NULL,
  uid         INT NOT NULL,
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (lab_id, uid),
  CONSTRAINT fk_li_lab FOREIGN KEY (lab_id) REFERENCES labs(lab_id) ON DELETE CASCADE,
  CONSTRAINT fk_li_user FOREIGN KEY (uid) REFERENCES users(uid) ON DELETE CASCADE
);

-- Add incharge_uid FK column to labs
ALTER TABLE labs
  ADD COLUMN IF NOT EXISTS incharge_uid INT NULL,
  ADD CONSTRAINT fk_labs_incharge_uid FOREIGN KEY (incharge_uid) REFERENCES users(uid) ON DELETE SET NULL ON UPDATE CASCADE;

-- ============================================================
-- 7. Link grievances to assets and workstations
-- ============================================================
ALTER TABLE grievances
  ADD COLUMN IF NOT EXISTS asset_id INT NULL,
  ADD COLUMN IF NOT EXISTS workstation_id INT NULL,
  ADD CONSTRAINT fk_grievances_asset FOREIGN KEY (asset_id) REFERENCES assets(asset_id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_grievances_workstation FOREIGN KEY (workstation_id) REFERENCES workstations(workstation_id) ON DELETE SET NULL,
  ADD INDEX IF NOT EXISTS idx_grievances_lab_status_created (lab_id, status, submission_date);
