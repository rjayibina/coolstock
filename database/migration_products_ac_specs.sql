-- Run this in phpMyAdmin's SQL tab on your mister_aircon database.
--
-- Phase 5 (final phase) of the ERD expansion: adds the remaining AC
-- technical spec fields from tblItem onto inventory_items. All nullable -
-- these are optional details, not every product (e.g. Consumable items
-- like filters or refrigerant canisters) will have all of them filled in.
--
-- Note on BrandCode: per the ERD, BrandCode belongs to the Brand record,
-- not the item (inventory_items already has brand_id from Phase 2, and
-- brands.brand_code is looked up via that FK wherever Brand is shown in
-- the UI). No brand_code column is added here - that would just duplicate
-- data that already lives on the brands table.

ALTER TABLE inventory_items
    ADD COLUMN model VARCHAR(100) DEFAULT NULL AFTER location_id,
    ADD COLUMN energy_rating VARCHAR(20) DEFAULT NULL AFTER model,
    ADD COLUMN monthly_consumption DECIMAL(10,2) DEFAULT NULL AFTER energy_rating,
    ADD COLUMN cooling_capacity VARCHAR(50) DEFAULT NULL AFTER monthly_consumption,
    ADD COLUMN refrigerant VARCHAR(50) DEFAULT NULL AFTER cooling_capacity,
    ADD COLUMN installation_type VARCHAR(50) DEFAULT NULL AFTER refrigerant,
    ADD COLUMN power_input VARCHAR(50) DEFAULT NULL AFTER installation_type,
    ADD COLUMN year INT DEFAULT NULL AFTER power_input;

-- Column notes:
--   monthly_consumption is numeric (kWh/month) so it can be summed/averaged later.
--   cooling_capacity and power_input are free-text (e.g. "1.5 HP (12,000 BTU/hr)",
--   "220-240V ~50Hz") since AC spec sheets mix units/formats depending on brand.
--   year is the unit's model year (e.g. 2024), not a date.
