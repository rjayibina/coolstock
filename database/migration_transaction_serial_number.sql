-- Run this in phpMyAdmin's SQL tab on your mister_aircon database.
--
-- Adds a `serial_number` column to transactions, so a Stock Out on a
-- serialized product (see migration_category_requires_serial.sql) can
-- log which unit's serial number was taken out, as part of the audit
-- trail. Not required for Stock In or for categories that don't use
-- serial numbers (tools, cleaning/repair materials) - stays NULL there.

ALTER TABLE transactions ADD COLUMN serial_number VARCHAR(100) DEFAULT NULL AFTER quantity;
