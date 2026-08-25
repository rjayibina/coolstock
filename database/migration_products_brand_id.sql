-- Run this in phpMyAdmin's SQL tab on your mister_aircon database.
--
-- Phase 2 of the ERD expansion: replaces the plain-text
-- inventory_items.brand column (added in migration_products_brand_replace_serial.sql)
-- with a proper brand_id FK to the brands table (created in
-- migration_create_brand_itemtype_location.sql).
--
-- ON DELETE RESTRICT matches how category_id already behaves on this table:
-- a Brand still referenced by a product can't be deleted (blocked at the DB
-- level, and also checked in the app before the delete is even attempted -
-- see Brand::hasLinkedItems()).
--
-- If you already have text values in the old `brand` column, migrate them
-- to matching rows in `brands` (adding any missing ones) BEFORE running the
-- DROP COLUMN below, or that data will be lost. For a fresh/dev database
-- with no real brand data yet, you can skip straight to the ALTER TABLE.

ALTER TABLE inventory_items
    ADD COLUMN brand_id INT NULL AFTER unit_of_measure,
    ADD CONSTRAINT fk_inventory_items_brand
        FOREIGN KEY (brand_id) REFERENCES brands(brand_id)
        ON DELETE RESTRICT;

ALTER TABLE inventory_items DROP COLUMN brand;
