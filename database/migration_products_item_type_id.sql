-- Run this in phpMyAdmin's SQL tab on your mister_aircon database.
--
-- Phase 3 of the ERD expansion: adds an item_type_id FK to inventory_items,
-- referencing the item_types table (Asset / Consumable) created in
-- migration_create_brand_itemtype_location.sql.
--
-- ON DELETE RESTRICT matches category_id and brand_id - an Item Type still
-- referenced by a product can't be deleted (blocked at the DB level, and
-- also checked in the app first - see ItemType::hasLinkedItems()).
--
-- Kept as a plain, independent field for now - it does NOT drive or
-- replace the category-level `requires_serial` flag. That flag continues
-- to work exactly as before; whether/how Item Type ties into serial
-- requirements is left for a later decision.

ALTER TABLE inventory_items
    ADD COLUMN item_type_id INT NULL AFTER brand_id,
    ADD CONSTRAINT fk_inventory_items_item_type
        FOREIGN KEY (item_type_id) REFERENCES item_types(item_type_id)
        ON DELETE RESTRICT;
