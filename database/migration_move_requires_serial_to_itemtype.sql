-- Run this in phpMyAdmin's SQL tab on your mister_aircon database.
--
-- Moves the "requires Serial Number on Stock Out" flag from item_categories
-- to item_types - it makes more sense there (an Asset gets a serial number
-- when taken out; a Consumable doesn't, regardless of category).
--
-- Seeds Asset = 1 (required) and Consumable = 0 (not required), matching
-- the ERD's example values. New item types default to requires_serial = 1
-- (checked), same "safer default" convention Category used to have -
-- uncheck it from the Item Type's Edit modal for anything consumable-like.
--
-- The old item_categories.requires_serial column is dropped entirely -
-- this project's convention is to fully remove superseded fields rather
-- than leave unused ones around.
--
-- A product with NO Item Type assigned (it's optional) is treated as
-- REQUIRING a serial number on Stock Out - the safer default, so a serial
-- doesn't accidentally go uncaptured just because Item Type was left blank.
-- See InventoryItem::readAll()/readOne() (COALESCE(t.requires_serial, 1)).

ALTER TABLE item_types
    ADD COLUMN requires_serial TINYINT(1) NOT NULL DEFAULT 1 AFTER type_name;

UPDATE item_types SET requires_serial = 1 WHERE type_name = 'Asset';
UPDATE item_types SET requires_serial = 0 WHERE type_name = 'Consumable';

ALTER TABLE item_categories DROP COLUMN requires_serial;
