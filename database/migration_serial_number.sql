-- Run this in phpMyAdmin's SQL tab on your mister_aircon database.
-- Replaces the old "requires_serial" checkbox column with a proper
-- serial_number text field (optional per product).

ALTER TABLE inventory_items ADD COLUMN serial_number VARCHAR(100) DEFAULT NULL AFTER unit_of_measure;
ALTER TABLE inventory_items DROP COLUMN requires_serial;
