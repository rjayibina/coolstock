-- Run this in phpMyAdmin's SQL tab on your mister_aircon database.
--
-- Phase 3 of the strict-ERD-compliance rework: rebuilds inventory_items
-- down to strictly the ERD's tblItem fields. Item now has no name field
-- of its own - `model` becomes the required, de-facto display name
-- everywhere (table, search, dropdowns, CSV, the Transactions item
-- picker), since it's the closest thing tblItem has to one.
--
-- Removed entirely (not just hidden in the UI):
--   inventory_items.item_name        - Products had no other unique
--                                       display field, so `model` takes
--                                       over that role app-wide.
--   inventory_items.description
--   inventory_items.unit_of_measure
--   inventory_items.image_path       - product photo upload is gone
--                                       entirely, not just hidden.
--   inventory_items.location_id      - the FK to locations, plus its
--                                       constraint (fk_inventory_items_location).
--                                       Location stays as a standalone
--                                       lookup table (Locations page still
--                                       works) but is no longer linked to
--                                       products - Location::hasLinkedItems()
--                                       and its delete-guard were removed
--                                       from the app since there's nothing
--                                       left to guard against.
--
-- `model` goes from optional to NOT NULL, now that it's the item's only
-- name. The UPDATE below backfills any existing NULL/blank values first
-- so the ALTER doesn't fail - safe to skip on a fresh/empty database.

UPDATE inventory_items SET model = 'Unnamed Item' WHERE model IS NULL OR model = '';

ALTER TABLE inventory_items
    DROP FOREIGN KEY fk_inventory_items_location,
    DROP COLUMN item_name,
    DROP COLUMN description,
    DROP COLUMN unit_of_measure,
    DROP COLUMN image_path,
    DROP COLUMN location_id,
    MODIFY COLUMN model VARCHAR(100) NOT NULL;
