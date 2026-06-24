-- ══════════════════════════════════════════════════════════════
-- SANDEZI OA  Phase 2.2 Migration
-- MySQL 5.7 compatible · Idempotent (safe to run multiple times)
-- Run ONCE on the server BEFORE deploying Phase 2.2 PHP code
-- ══════════════════════════════════════════════════════════════

-- ─── 1. Expand leaves.status to include Revoked ──────────────
ALTER TABLE leaves
    MODIFY COLUMN status
        ENUM('Pending','Approved','Rejected','Revoked')
        NOT NULL DEFAULT 'Pending';

-- ─── 2. New columns on leaves ────────────────────────────────
--   original_schedules_json : stores pre-approval schedule rows
--                             (JSON) so approval can be reverted
--   approved_by             : user ID who approved
--   approved_at             : timestamp of approval
ALTER TABLE leaves
    ADD COLUMN original_schedules_json TEXT         NULL AFTER approve_remark,
    ADD COLUMN approved_by             INT          NULL AFTER original_schedules_json,
    ADD COLUMN approved_at             TIMESTAMP    NULL AFTER approved_by;

-- ─── 3. Track leave-sourced schedule rows ────────────────────
--   source_leave_id : links a schedule row to the leave that
--                     created it; NULL = manually assigned
ALTER TABLE schedules
    ADD COLUMN source_leave_id INT NULL AFTER remark;

-- ─── 4. System-shift flag on shifts table ────────────────────
--   is_system = 1 rows are auto-managed by the leave system;
--   they are hidden from the manual shift management UI
ALTER TABLE shifts
    ADD COLUMN is_system TINYINT(1) NOT NULL DEFAULT 0 AFTER description;

-- ─── 5. System shifts (one per leave / trip / out type) ──────
--   Idempotent: INSERT only if name+is_system row is absent

INSERT INTO shifts (name, start_time, end_time, description, is_system)
SELECT '年假','00:00:00','00:00:00','年假（系统自动）',1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shifts WHERE name='年假'  AND is_system=1);

INSERT INTO shifts (name, start_time, end_time, description, is_system)
SELECT '病假','00:00:00','00:00:00','病假（系统自动）',1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shifts WHERE name='病假'  AND is_system=1);

INSERT INTO shifts (name, start_time, end_time, description, is_system)
SELECT '事假','00:00:00','00:00:00','事假（系统自动）',1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shifts WHERE name='事假'  AND is_system=1);

INSERT INTO shifts (name, start_time, end_time, description, is_system)
SELECT '婚假','00:00:00','00:00:00','婚假（系统自动）',1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shifts WHERE name='婚假'  AND is_system=1);

INSERT INTO shifts (name, start_time, end_time, description, is_system)
SELECT '产假','00:00:00','00:00:00','产假（系统自动）',1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shifts WHERE name='产假'  AND is_system=1);

INSERT INTO shifts (name, start_time, end_time, description, is_system)
SELECT '陪产假','00:00:00','00:00:00','陪产假（系统自动）',1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shifts WHERE name='陪产假' AND is_system=1);

INSERT INTO shifts (name, start_time, end_time, description, is_system)
SELECT '丧假','00:00:00','00:00:00','丧假（系统自动）',1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shifts WHERE name='丧假'  AND is_system=1);

INSERT INTO shifts (name, start_time, end_time, description, is_system)
SELECT '调休','00:00:00','00:00:00','调休（系统自动）',1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shifts WHERE name='调休'  AND is_system=1);

INSERT INTO shifts (name, start_time, end_time, description, is_system)
SELECT '出差','00:00:00','00:00:00','出差（系统自动）',1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shifts WHERE name='出差'  AND is_system=1);

INSERT INTO shifts (name, start_time, end_time, description, is_system)
SELECT '外出','00:00:00','00:00:00','外出（系统自动）',1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shifts WHERE name='外出'  AND is_system=1);

INSERT INTO shifts (name, start_time, end_time, description, is_system)
SELECT '其他','00:00:00','00:00:00','其他假期（系统自动）',1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shifts WHERE name='其他'  AND is_system=1);

-- ══════════════════════════════════════════════════════════════
-- END OF MIGRATION
-- ══════════════════════════════════════════════════════════════
