-- Run this in phpMyAdmin's SQL tab on your mister_aircon database.
--
-- Reverses part of migration_strip_item_to_erd_fields.sql: Location is
-- linked back to Products. Re-adds `location_id` as a nullable FK on
-- inventory_items, same shape as before it was dropped (ON DELETE
-- RESTRICT, same constraint name), one location per product.
--
-- Locations itself keeps its simplified ID + Name list page (no Products
-- column/filter/sort) - only the underlying link and its delete-guard
-- (Location::hasLinkedItems()) come back, not the count display.

ALTER TABLE inventory_items
    ADD COLUMN location_id INT NULL AFTER item_type_id,
    ADD CONSTRAINT fk_inventory_items_location
        FOREIGN KEY (location_id) REFERENCES locations(location_id)
        ON DELETE RESTRICT;
