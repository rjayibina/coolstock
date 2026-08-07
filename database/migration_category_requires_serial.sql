-- Run this in phpMyAdmin's SQL tab on your mister_aircon database.
--
-- Adds a `requires_serial` flag to item_categories. Whole units and unit
-- parts get serial-numbered, so their categories should keep this at 1
-- (the default). Tools and cleaning/repair materials don't have serial
-- numbers, so their categories should have this unchecked (0) - do that
-- from the category's Edit modal after running this migration.
--
-- This flag is what the Stock Out modal checks to decide whether to show
-- and require the Serial Number field for a given product.

ALTER TABLE item_categories
    ADD COLUMN requires_serial TINYINT(1) NOT NULL DEFAULT 1 AFTER category_description;
