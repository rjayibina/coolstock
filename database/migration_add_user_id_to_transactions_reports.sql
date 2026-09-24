-- Adds a real foreign key from transactions/reports back to the users
-- table, alongside the existing free-text technician_name/generated_by
-- columns (kept intentionally as an audit-trail "name at the time"
-- snapshot - see the TODO comment already in Models/Transaction.php).
--
-- user_id is nullable and ON DELETE SET NULL because a user account can
-- be deactivated (is_active = 0) rather than deleted, but if a row's
-- user_id ever loses its target, the historical technician_name/
-- generated_by text still tells you who did it.

ALTER TABLE transactions
    ADD COLUMN user_id INT(11) DEFAULT NULL AFTER technician_name,
    ADD KEY fk_transactions_user (user_id),
    ADD CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE SET NULL;

ALTER TABLE reports
    ADD COLUMN user_id INT(11) DEFAULT NULL AFTER generated_by,
    ADD KEY fk_reports_user (user_id),
    ADD CONSTRAINT fk_reports_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE SET NULL;

-- One-time backfill for existing rows: match the free-text name against
-- users.full_name. Safe to re-run - only fills rows that are still NULL.
UPDATE transactions t
    JOIN users u ON u.full_name = t.technician_name
    SET t.user_id = u.user_id
    WHERE t.user_id IS NULL;

UPDATE reports r
    JOIN users u ON u.full_name = r.generated_by
    SET r.user_id = u.user_id
    WHERE r.user_id IS NULL;
