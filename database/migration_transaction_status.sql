-- Run this in phpMyAdmin's SQL tab on your mister_aircon database.
-- Adds a `status` column so Item Request transactions can sit as
-- 'pending' (no stock deducted yet) until approved (-> 'completed',
-- stock deducted) or declined (-> 'declined', stock never touched,
-- but the record stays for the audit trail). Every other transaction
-- type is created as 'completed' immediately, same as before.

ALTER TABLE transactions ADD COLUMN status ENUM('pending', 'completed', 'declined') NOT NULL DEFAULT 'completed' AFTER source;
