-- Run this in phpMyAdmin's SQL tab on your mister_aircon database.
-- Adds a `source` column to transactions so the system can tell manually
-- logged transactions apart from ones it generates automatically (when a
-- product is created with starting stock, or its quantity is edited
-- directly). Auto rows won't show a Delete action in the UI.

ALTER TABLE transactions ADD COLUMN source ENUM('manual', 'auto') NOT NULL DEFAULT 'manual' AFTER notes;
