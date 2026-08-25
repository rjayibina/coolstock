-- Run this in phpMyAdmin's SQL tab on your mister_aircon database.
--
-- Phase 4 of the ERD expansion: adds a location_id FK to inventory_items,
-- referencing the locations table (Main Store / Warehouse) created in
-- migration_create_brand_itemtype_location.sql.
--
-- Confirmed design: one location per product (a plain FK column), NOT
-- per-location quantity splitting - no junction/quantity-by-location table.
--
-- ON DELETE RESTRICT matches category_id / brand_id / item_type_id - a
-- Location still referenced by a product can't be deleted (blocked at the
-- DB level, and also checked in the app first - see Location::hasLinkedItems()).

ALTER TABLE inventory_items
    ADD COLUMN location_id INT NULL AFTER item_type_id,
    ADD CONSTRAINT fk_inventory_items_location
        FOREIGN KEY (location_id) REFERENCES locations(location_id)
        ON DELETE RESTRICT;
