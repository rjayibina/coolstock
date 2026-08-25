-- Run this in phpMyAdmin's SQL tab on your mister_aircon database, AFTER
-- every migration in database/ has been applied (this script assumes the
-- full Phase 1-5 schema already exists: brands, item_types, locations,
-- and all the FK/spec columns on inventory_items).
--
-- WARNING: this WIPES every row in item_categories, brands, item_types,
-- locations, inventory_items, transactions, and reports, and resets their
-- auto-increment counters back to 1. Use this on a fresh dev/demo database,
-- not on one with real data you want to keep.
--
-- What it seeds:
--   5  categories   (4 AC unit types from the ERD + 1 consumables/parts bucket)
--   6  brands        (real manufacturers, each with its brand_code)
--   2  item types    (Asset, Consumable - same as the ERD example values)
--   2  locations     (Main Store, Warehouse - same as the ERD example values)
--   11 inventory items (7 AC units with full technical specs, 4 consumables)
--   12 transactions   (stock-in history, a couple of stock-outs with serial
--                      numbers, one pending Item Request, one declined one)
--
-- Quantities are deliberately set so the Dashboard's Low Stock / Out of
-- Stock widgets have something real to show (see items #4 and #11 below).

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE transactions;
TRUNCATE TABLE reports;
TRUNCATE TABLE inventory_items;
TRUNCATE TABLE item_categories;
TRUNCATE TABLE brands;
TRUNCATE TABLE item_types;
TRUNCATE TABLE locations;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Categories (category_id 1-5)
-- ============================================================
INSERT INTO item_categories (category_name, category_description, requires_serial) VALUES
('Split Type AC', 'Wall-mounted split-type air conditioning units, indoor + outdoor unit pairs.', 1),
('Window Type AC', 'Self-contained window/wall-box air conditioning units.', 1),
('Floor Mounted AC', 'Floor-standing air conditioning units for larger open areas.', 1),
('Cassette Type AC', 'Ceiling cassette air conditioning units for commercial spaces.', 1),
('Consumables & Spare Parts', 'Refrigerant, insulation, mounting hardware, and other non-serialized supplies.', 0);

-- ============================================================
-- Brands (brand_id 1-6)
-- ============================================================
INSERT INTO brands (brand_name, brand_code) VALUES
('Daikin', '045'),
('Carrier', '088'),
('Panasonic', '112'),
('LG', '076'),
('Samsung', '059'),
('Mitsubishi Electric', '033');

-- ============================================================
-- Item Types (item_type_id 1-2) - same example values as the ERD sketch
-- ============================================================
INSERT INTO item_types (type_name) VALUES ('Asset'), ('Consumable');

-- ============================================================
-- Locations (location_id 1-2) - same example values as the ERD sketch
-- ============================================================
INSERT INTO locations (location_name) VALUES ('Main Store'), ('Warehouse');

-- ============================================================
-- Inventory Items (item_id 1-11)
-- ============================================================

-- #1 - Daikin Split Type, Main Store, healthy stock
INSERT INTO inventory_items
    (category_id, item_name, description, unit_of_measure, brand_id, item_type_id, location_id,
     model, energy_rating, monthly_consumption, cooling_capacity, refrigerant, installation_type, power_input, year,
     quantity_on_hand, minimum_stock_level)
VALUES
    (1, 'Daikin Split Type Inverter AC 1.0HP', 'Inverter split-type air conditioner, ideal for bedrooms and small offices.', 'unit', 1, 1, 1,
     'FTKC25XVM', '5 Star (Inverter)', 45.50, '9,000 BTU/hr', 'R32', 'Wall Mounted', '220-240V, 50Hz, 1.5A', 2024,
     15, 5);

-- #2 - Carrier Window Type, Warehouse
INSERT INTO inventory_items
    (category_id, item_name, description, unit_of_measure, brand_id, item_type_id, location_id,
     model, energy_rating, monthly_consumption, cooling_capacity, refrigerant, installation_type, power_input, year,
     quantity_on_hand, minimum_stock_level)
VALUES
    (2, 'Carrier Window Type AC 1.5HP', 'Compact window-mounted unit, straightforward install for single rooms.', 'unit', 2, 1, 2,
     '51QAC12', '3 Star', 68.00, '12,000 BTU/hr', 'R410A', 'Window Mounted', '220-240V, 50Hz, 5.8A', 2023,
     8, 3);

-- #3 - Panasonic Floor Mounted, Main Store
INSERT INTO inventory_items
    (category_id, item_name, description, unit_of_measure, brand_id, item_type_id, location_id,
     model, energy_rating, monthly_consumption, cooling_capacity, refrigerant, installation_type, power_input, year,
     quantity_on_hand, minimum_stock_level)
VALUES
    (3, 'Panasonic Floor Mounted AC 2.0HP', 'Floor-standing unit for wide open areas like showrooms or lobbies.', 'unit', 3, 1, 1,
     'S-24PU1U5B', '4 Star', 95.20, '24,000 BTU/hr', 'R32', 'Floor Standing', '220-240V, 50Hz, 9.5A', 2024,
     5, 2);

-- #4 - LG Cassette Type, Warehouse - LOW STOCK on purpose (1 on hand, min 2)
INSERT INTO inventory_items
    (category_id, item_name, description, unit_of_measure, brand_id, item_type_id, location_id,
     model, energy_rating, monthly_consumption, cooling_capacity, refrigerant, installation_type, power_input, year,
     quantity_on_hand, minimum_stock_level)
VALUES
    (4, 'LG Cassette Type AC 3.0HP', 'Ceiling cassette unit for commercial spaces, 4-way air discharge.', 'unit', 4, 1, 2,
     'ATNQ36GPLE0', '3 Star', 145.00, '36,000 BTU/hr', 'R410A', 'Ceiling Cassette', '380-415V, 3-Phase, 50Hz', 2022,
     1, 2);

-- #5 - Samsung Split Type, Main Store
INSERT INTO inventory_items
    (category_id, item_name, description, unit_of_measure, brand_id, item_type_id, location_id,
     model, energy_rating, monthly_consumption, cooling_capacity, refrigerant, installation_type, power_input, year,
     quantity_on_hand, minimum_stock_level)
VALUES
    (1, 'Samsung Split Type Inverter AC 1.5HP', 'Inverter split-type unit with WindFree cooling technology.', 'unit', 5, 1, 1,
     'AR13AYHZAWK', '5 Star', 58.30, '13,000 BTU/hr', 'R32', 'Wall Mounted', '220-240V, 50Hz, 2.3A', 2024,
     20, 6);

-- #6 - Mitsubishi Electric Split Type, Warehouse
INSERT INTO inventory_items
    (category_id, item_name, description, unit_of_measure, brand_id, item_type_id, location_id,
     model, energy_rating, monthly_consumption, cooling_capacity, refrigerant, installation_type, power_input, year,
     quantity_on_hand, minimum_stock_level)
VALUES
    (1, 'Mitsubishi Electric Split Type AC 2.5HP', 'Inverter split-type unit, quiet operation, wide temperature range.', 'unit', 6, 1, 2,
     'MSY-GL25VF', '5 Star (Inverter)', 82.00, '25,000 BTU/hr', 'R32', 'Wall Mounted', '220-240V, 50Hz, 3.8A', 2023,
     6, 2);

-- #7 - R32 Refrigerant Gas Cylinder (Consumable, no brand, no AC specs)
INSERT INTO inventory_items
    (category_id, item_name, description, unit_of_measure, brand_id, item_type_id, location_id,
     model, energy_rating, monthly_consumption, cooling_capacity, refrigerant, installation_type, power_input, year,
     quantity_on_hand, minimum_stock_level)
VALUES
    (5, 'R32 Refrigerant Gas Cylinder (10kg)', 'Refrigerant gas cylinder for AC recharging and installation jobs.', 'cylinder', NULL, 2, 1,
     NULL, NULL, NULL, NULL, 'R32', NULL, NULL, NULL,
     25, 10);

-- #8 - Copper Pipe Insulation Tape (Consumable)
INSERT INTO inventory_items
    (category_id, item_name, description, unit_of_measure, brand_id, item_type_id, location_id,
     quantity_on_hand, minimum_stock_level)
VALUES
    (5, 'Copper Pipe Insulation Tape', 'Foam insulation tape for copper refrigerant lines.', 'roll', NULL, 2, 1,
     60, 20);

-- #9 - PVC Drain Pipe (Consumable)
INSERT INTO inventory_items
    (category_id, item_name, description, unit_of_measure, brand_id, item_type_id, location_id,
     quantity_on_hand, minimum_stock_level)
VALUES
    (5, 'PVC Drain Pipe 1/2 inch', 'PVC drain pipe for AC condensate drainage.', 'meter', NULL, 2, 2,
     200, 50);

-- #10 - AC Mounting Bracket Set (Consumable)
INSERT INTO inventory_items
    (category_id, item_name, description, unit_of_measure, brand_id, item_type_id, location_id,
     quantity_on_hand, minimum_stock_level)
VALUES
    (5, 'AC Mounting Bracket Set (Wall Type)', 'Heavy-duty wall mounting bracket set for split-type outdoor units.', 'set', NULL, 2, 2,
     30, 10);

-- #11 - Daikin Window Type, Main Store - OUT OF STOCK on purpose (0 on hand)
INSERT INTO inventory_items
    (category_id, item_name, description, unit_of_measure, brand_id, item_type_id, location_id,
     model, energy_rating, monthly_consumption, cooling_capacity, refrigerant, installation_type, power_input, year,
     quantity_on_hand, minimum_stock_level)
VALUES
    (2, 'Daikin Window Type AC 1.0HP', 'Compact window-mounted unit for single rooms.', 'unit', 1, 1, 1,
     'FACQ10', '3 Star', 52.00, '10,000 BTU/hr', 'R32', 'Window Mounted', '220-240V, 50Hz, 4.5A', 2022,
     0, 3);

-- ============================================================
-- Transactions - audit trail history for the seeded items
-- ============================================================

-- #1 Daikin Split 1.0HP: stocked in 20, later 5 went out with a serial (net 15)
INSERT INTO transactions (item_id, transaction_type, quantity, serial_number, transaction_date, technician_name, notes, source, status) VALUES
(1, 'stock_in', 20, NULL, '2026-06-15', NULL, 'Initial stock (seed data).', 'auto', 'completed'),
(1, 'stock_out', 5, 'DK-SN-2024-0011', '2026-07-20', 'Roberto Cruz', 'Installed at client site - Barangay Lahug.', 'manual', 'completed');

-- #2 Carrier Window 1.5HP: stocked in 8, plus one declined stock-out (never deducted)
INSERT INTO transactions (item_id, transaction_type, quantity, serial_number, transaction_date, technician_name, notes, source, status) VALUES
(2, 'stock_in', 8, NULL, '2026-06-20', NULL, 'Initial stock (seed data).', 'auto', 'completed'),
(2, 'stock_out', 2, 'CR-SN-2023-0078', '2026-08-05', 'Maria Santos', 'Declined - insufficient authorization, needs supervisor approval.', 'manual', 'declined');

-- #3 Panasonic Floor Mounted 2.0HP: single stock-in
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, notes, source, status) VALUES
(3, 'stock_in', 5, '2026-07-01', 'Initial stock (seed data).', 'auto', 'completed');

-- #4 LG Cassette 3.0HP: stocked in 3, 2 went out with a serial (net 1 - low stock)
INSERT INTO transactions (item_id, transaction_type, quantity, serial_number, transaction_date, technician_name, notes, source, status) VALUES
(4, 'stock_in', 3, NULL, '2026-05-10', NULL, 'Initial stock (seed data).', 'auto', 'completed'),
(4, 'stock_out', 2, 'LG-SN-2022-0087', '2026-08-01', 'Ana Reyes', 'Installed - conference room unit.', 'manual', 'completed');

-- #5 Samsung Split 1.5HP: stocked in 25, 5 went out with a serial (net 20)
INSERT INTO transactions (item_id, transaction_type, quantity, serial_number, transaction_date, technician_name, notes, source, status) VALUES
(5, 'stock_in', 25, NULL, '2026-06-01', NULL, 'Initial stock (seed data).', 'auto', 'completed'),
(5, 'stock_out', 5, 'SM-SN-2024-0456', '2026-07-10', 'Roberto Cruz', 'Client delivery - residential installation.', 'manual', 'completed');

-- #6 Mitsubishi Electric Split 2.5HP: single stock-in
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, notes, source, status) VALUES
(6, 'stock_in', 6, '2026-06-25', 'Initial stock (seed data).', 'auto', 'completed');

-- #7 R32 Refrigerant Gas: stocked in 25, plus a pending Item Request (no stock deducted yet)
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, notes, source, status) VALUES
(7, 'stock_in', 25, '2026-06-05', 'Initial stock (seed data).', 'auto', 'completed');
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(7, 'item_request', 5, '2026-08-20', 'Juan Dela Cruz', 'Requested for scheduled maintenance job - PO#2026-0892.', 'manual', 'pending');

-- #8, #9, #10 - consumables, single stock-in each
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, notes, source, status) VALUES
(8, 'stock_in', 60, '2026-06-05', 'Initial stock (seed data).', 'auto', 'completed'),
(9, 'stock_in', 200, '2026-06-05', 'Initial stock (seed data).', 'auto', 'completed'),
(10, 'stock_in', 30, '2026-06-05', 'Initial stock (seed data).', 'auto', 'completed');

-- #11 Daikin Window 1.0HP: stocked in 3, all 3 went out with a serial (net 0 - out of stock)
INSERT INTO transactions (item_id, transaction_type, quantity, serial_number, transaction_date, technician_name, notes, source, status) VALUES
(11, 'stock_in', 3, NULL, '2026-04-15', NULL, 'Initial stock (seed data).', 'auto', 'completed'),
(11, 'stock_out', 3, 'DK-SN-2022-0033', '2026-06-25', 'Ana Reyes', 'Last unit sold - awaiting restock.', 'manual', 'completed');
