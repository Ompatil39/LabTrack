-- Phase 2: Data Model - DOWN (rollback)
-- Drops all tables and columns added in 002_phase2_data_model.sql in reverse dependency order

-- 1. Remove FK columns from grievances
ALTER TABLE grievances
  DROP FOREIGN KEY IF EXISTS fk_grievances_workstation,
  DROP FOREIGN KEY IF EXISTS fk_grievances_asset,
  DROP COLUMN IF EXISTS workstation_id,
  DROP COLUMN IF EXISTS asset_id;

-- 2. Remove incharge_uid FK from labs
ALTER TABLE labs
  DROP FOREIGN KEY IF EXISTS fk_labs_incharge_uid,
  DROP COLUMN IF EXISTS incharge_uid;

-- 3. Drop lab_incharges
DROP TABLE IF EXISTS lab_incharges;

-- 4. Drop asset_assignments (depends on assets & workstations)
DROP TABLE IF EXISTS asset_assignments;

-- 5. Drop asset_tag_sequences
DROP TABLE IF EXISTS asset_tag_sequences;

-- 6. Drop assets
DROP TABLE IF EXISTS assets;

-- 7. Drop workstations
DROP TABLE IF EXISTS workstations;

-- 8. Remove uid column from users
ALTER TABLE users
  DROP COLUMN IF EXISTS uid;
