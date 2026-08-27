-- Run this in phpMyAdmin's SQL tab on your mister_aircon database.
--
-- Major schema simplification: strips the Brand, Category, Item Type, and
-- Location tables down to strictly ID + Name, matching the hand-drawn ERD.
--
-- Removed entirely (not just hidden in the UI):
--   brands.brand_code           - Brand Code display/filter on Products is
--                                  gone; Add/Edit Product no longer shows it.
--   brands.created_at
--   item_categories.category_description
--   item_categories.created_at
--   item_types.requires_serial  - the "Requires Serial Number on Stock Out"
--                                  flag (Asset/Consumable). Stock Out no
--                                  longer conditionally requires a serial
--                                  number for any item type - the Serial
--                                  Number field on Stock Out is now always
--                                  optional, for any product.
--   item_types.created_at
--   locations.created_at
--
-- Consequence for the Brands/Categories/Item Types/Locations list pages:
-- the "Recently added / Oldest first" sort option is gone (no more
-- created_at to sort by) - Name and Product Count sorting still work.

ALTER TABLE brands
    DROP COLUMN brand_code,
    DROP COLUMN created_at;

ALTER TABLE item_categories
    DROP COLUMN category_description,
    DROP COLUMN created_at;

ALTER TABLE item_types
    DROP COLUMN requires_serial,
    DROP COLUMN created_at;

ALTER TABLE locations
    DROP COLUMN created_at;
