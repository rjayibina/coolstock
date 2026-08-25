-- Run this in phpMyAdmin's SQL tab on your mister_aircon database.
-- Adds a `transaction_date` column so a Stock In / Stock Out entry can
-- record the date the stock movement actually happened (e.g. backdating
-- a delivery), separate from `created_at` which is just the audit
-- timestamp of when the row was logged in the system.
-- Defaults existing rows to the date portion of their created_at so
-- nothing ends up NULL after the migration runs.

ALTER TABLE transactions ADD COLUMN transaction_date DATE DEFAULT NULL AFTER quantity;

UPDATE transactions SET transaction_date = DATE(created_at) WHERE transaction_date IS NULL;
