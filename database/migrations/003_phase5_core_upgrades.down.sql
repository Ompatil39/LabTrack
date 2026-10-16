-- Rollback Migration: 003_phase5_core_upgrades.down.sql
-- Description: Revert Phase 5 core upgrades

DROP TABLE IF EXISTS maintenance_records;
DROP TABLE IF EXISTS notification_jobs;
DROP TABLE IF EXISTS ticket_status_history;
DROP TABLE IF EXISTS ticket_comments;
DROP VIEW IF EXISTS tickets;

ALTER TABLE grievances
    DROP COLUMN IF EXISTS tracking_code,
    DROP COLUMN IF EXISTS priority,
    DROP COLUMN IF EXISTS assigned_to_uid,
    DROP COLUMN IF EXISTS escalated_to_admin,
    DROP COLUMN IF EXISTS first_responded_at,
    DROP COLUMN IF EXISTS resolved_at,
    DROP COLUMN IF EXISTS sla_response_due_at,
    DROP COLUMN IF EXISTS sla_resolve_due_at;

ALTER TABLE assets
    DROP COLUMN IF EXISTS salvage_value,
    DROP COLUMN IF EXISTS useful_life_years,
    DROP COLUMN IF EXISTS retired_at,
    DROP COLUMN IF EXISTS retire_reason;

DROP TABLE IF EXISTS sla_policies;
DROP TABLE IF EXISTS audit_logs;
