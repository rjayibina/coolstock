-- Run this ONLY if you already ran migration_transaction_status.sql
-- (i.e. `status` column already exists on transactions) and it does
-- NOT yet include 'declined' as an option. Safe to run even if it's
-- already there - MySQL will just leave it as-is.
-- If you haven't run migration_transaction_status.sql at all yet,
-- ignore this file - the other one already includes 'declined'.

ALTER TABLE transactions MODIFY COLUMN status ENUM('pending', 'completed', 'declined') NOT NULL DEFAULT 'completed';
