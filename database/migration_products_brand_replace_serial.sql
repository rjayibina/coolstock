-- Run this in phpMyAdmin's SQL tab on your mister_aircon database.
--
-- Phase 2 of the serial number rework: products no longer carry their own
-- serial_number column at all - it's fully replaced by transactions.serial_number
-- (added back in migration_transaction_serial_number.sql), which is filled
-- in per Stock Out event on categories that require one.
--
-- In its place, products gain a `brand` column, positioned next to
-- unit_of_measure to match where it shows up in the UI.

ALTER TABLE inventory_items DROP COLUMN serial_number;
ALTER TABLE inventory_items ADD COLUMN brand VARCHAR(100) DEFAULT NULL AFTER unit_of_measure;
