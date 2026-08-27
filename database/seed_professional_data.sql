-- Run this in phpMyAdmin's SQL tab on your mister_aircon database, AFTER
-- every migration in database/ has been applied (this script assumes the
-- full current schema already exists: item_categories/brands/item_types
-- stripped to ID + Name, inventory_items with `model` as its required
-- display name plus location_id linking it to locations, technical specs
-- required only for the Asset item type, and transactions with no
-- stock-quantity effect).
--
-- WARNING: this WIPES every row in item_categories, brands, item_types,
-- locations, inventory_items, transactions, and reports, and resets their
-- auto-increment counters back to 1. Use this on a fresh dev/demo database,
-- not on one with real data you want to keep.
--
-- What it seeds:
--   5  categories      (4 AC unit types from the ERD + 1 consumables/parts bucket)
--   6  brands          (real manufacturers, name only - no brand_code anymore)
--   2  item types      (Asset, Consumable - same as the ERD example values)
--   2  locations       (Main Store, Warehouse - same as the ERD example values,
--                       linked to items via location_id)
--   11 inventory items (7 Asset AC units with full technical specs, 4
--                       Consumables with none) - each identified by
--                       `model` alone, since Item has no separate name
--                       field anymore
--   13 transactions    (Item Request / Borrow / Return activity log entries -
--                       a mix of completed, one pending, and one declined
--                       Item Request, so every status has a real example)
--
-- There is no stock-level concept anymore, so nothing here simulates a
-- "low stock" or "out of stock" state - that widget is gone from the app.

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
INSERT INTO item_categories (category_name) VALUES
('Split Type AC'),
('Window Type AC'),
('Floor Mounted AC'),
('Cassette Type AC'),
('Consumables & Spare Parts');

-- ============================================================
-- Brands (brand_id 1-6)
-- ============================================================
INSERT INTO brands (brand_name) VALUES
('Daikin'),
('Carrier'),
('Panasonic'),
('LG'),
('Samsung'),
('Mitsubishi Electric');

-- ============================================================
-- Item Types (item_type_id 1-2) - same example values as the ERD sketch
-- ============================================================
INSERT INTO item_types (type_name) VALUES ('Asset'), ('Consumable');

-- ============================================================
-- Locations (location_id 1-2) - same example values as the ERD sketch,
-- linked to items below via location_id.
-- ============================================================
INSERT INTO locations (location_name) VALUES ('Main Store'), ('Warehouse');

-- ============================================================
-- Inventory Items (item_id 1-11)
-- Each row is: category_id, brand_id, item_type_id, location_id, model,
-- energy_rating, monthly_consumption, cooling_capacity, refrigerant,
-- installation_type, power_input, year. Consumables (#7-10) have no
-- brand or AC specs, so their `model` is a short part code instead of a
-- manufacturer model number. Every Asset item (#1-6, #11) has all 7
-- technical specs filled in, since the app requires them for Asset;
-- Consumables (#7-10) have none, since the app hides and blanks that
-- section for Consumable/no item type.
-- ============================================================

-- #1 - Daikin Split Type, Main Store
INSERT INTO inventory_items
    (category_id, brand_id, item_type_id, location_id, model, energy_rating, monthly_consumption, cooling_capacity, refrigerant, installation_type, power_input, year)
VALUES
    (1, 1, 1, 1, 'FTKC25XVM', '5 Star (Inverter)', 45.50, '9,000 BTU/hr', 'R32', 'Wall Mounted', '220-240V, 50Hz, 1.5A', 2024);

-- #2 - Carrier Window Type, Warehouse
INSERT INTO inventory_items
    (category_id, brand_id, item_type_id, location_id, model, energy_rating, monthly_consumption, cooling_capacity, refrigerant, installation_type, power_input, year)
VALUES
    (2, 2, 1, 2, '51QAC12', '3 Star', 68.00, '12,000 BTU/hr', 'R410A', 'Window Mounted', '220-240V, 50Hz, 5.8A', 2023);

-- #3 - Panasonic Floor Mounted, Main Store
INSERT INTO inventory_items
    (category_id, brand_id, item_type_id, location_id, model, energy_rating, monthly_consumption, cooling_capacity, refrigerant, installation_type, power_input, year)
VALUES
    (3, 3, 1, 1, 'S-24PU1U5B', '4 Star', 95.20, '24,000 BTU/hr', 'R32', 'Floor Standing', '220-240V, 50Hz, 9.5A', 2024);

-- #4 - LG Cassette Type, Warehouse
INSERT INTO inventory_items
    (category_id, brand_id, item_type_id, location_id, model, energy_rating, monthly_consumption, cooling_capacity, refrigerant, installation_type, power_input, year)
VALUES
    (4, 4, 1, 2, 'ATNQ36GPLE0', '3 Star', 145.00, '36,000 BTU/hr', 'R410A', 'Ceiling Cassette', '380-415V, 3-Phase, 50Hz', 2022);

-- #5 - Samsung Split Type, Main Store
INSERT INTO inventory_items
    (category_id, brand_id, item_type_id, location_id, model, energy_rating, monthly_consumption, cooling_capacity, refrigerant, installation_type, power_input, year)
VALUES
    (1, 5, 1, 1, 'AR13AYHZAWK', '5 Star', 58.30, '13,000 BTU/hr', 'R32', 'Wall Mounted', '220-240V, 50Hz, 2.3A', 2024);

-- #6 - Mitsubishi Electric Split Type, Warehouse
INSERT INTO inventory_items
    (category_id, brand_id, item_type_id, location_id, model, energy_rating, monthly_consumption, cooling_capacity, refrigerant, installation_type, power_input, year)
VALUES
    (1, 6, 1, 2, 'MSY-GL25VF', '5 Star (Inverter)', 82.00, '25,000 BTU/hr', 'R32', 'Wall Mounted', '220-240V, 50Hz, 3.8A', 2023);

-- #7 - R32 Refrigerant Gas Cylinder, Main Store (Consumable, no brand, no AC specs)
INSERT INTO inventory_items
    (category_id, brand_id, item_type_id, location_id, model)
VALUES
    (5, NULL, 2, 1, 'R32-CYL-10KG');

-- #8 - Copper Pipe Insulation Tape, Main Store (Consumable)
INSERT INTO inventory_items
    (category_id, brand_id, item_type_id, location_id, model)
VALUES
    (5, NULL, 2, 1, 'INSUL-TAPE-CU');

-- #9 - PVC Drain Pipe, Warehouse (Consumable)
INSERT INTO inventory_items
    (category_id, brand_id, item_type_id, location_id, model)
VALUES
    (5, NULL, 2, 2, 'PVC-DRAIN-12');

-- #10 - AC Mounting Bracket Set, Warehouse (Consumable)
INSERT INTO inventory_items
    (category_id, brand_id, item_type_id, location_id, model)
VALUES
    (5, NULL, 2, 2, 'BRKT-WALL-SET');

-- #11 - Daikin Window Type, Main Store
INSERT INTO inventory_items
    (category_id, brand_id, item_type_id, location_id, model, energy_rating, monthly_consumption, cooling_capacity, refrigerant, installation_type, power_input, year)
VALUES
    (2, 1, 1, 1, 'FACQ10', '3 Star', 52.00, '10,000 BTU/hr', 'R32', 'Window Mounted', '220-240V, 50Hz, 4.5A', 2022);

-- ============================================================
-- Transactions - activity log for the seeded items. No quantity math -
-- these are just records that a movement happened. transaction_type is
-- one of item_request / borrow / return; status is completed unless
-- noted otherwise (item_request also supports pending and declined).
-- ============================================================

-- #1 Daikin Split FTKC25XVM - Item Request, completed
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(1, 'item_request', 2, '2026-07-20', 'Roberto Cruz', 'Requested for an installation job at Barangay Lahug.', 'manual', 'completed');

-- #2 Carrier Window 51QAC12 - Item Request, declined
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(2, 'item_request', 1, '2026-08-05', 'Maria Santos', 'Declined - insufficient documentation, needs supervisor approval.', 'manual', 'declined');

-- #3 Panasonic Floor S-24PU1U5B - Borrowed for a trade show, then returned
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(3, 'borrow', 1, '2026-07-01', 'Ana Reyes', 'Borrowed for a trade show display unit.', 'manual', 'completed'),
(3, 'return', 1, '2026-07-08', 'Ana Reyes', 'Returned after the trade show.', 'manual', 'completed');

-- #4 LG Cassette ATNQ36GPLE0 - Item Request, completed
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(4, 'item_request', 1, '2026-08-01', 'Roberto Cruz', 'Requested for a commercial installation - conference room unit.', 'manual', 'completed');

-- #5 Samsung Split AR13AYHZAWK - Item Request, completed
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(5, 'item_request', 1, '2026-07-10', 'Roberto Cruz', 'Requested for a residential installation - client delivery.', 'manual', 'completed');

-- #6 Mitsubishi Electric MSY-GL25VF - Borrowed for a site survey
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(6, 'borrow', 1, '2026-06-25', 'Juan Dela Cruz', 'Borrowed for a client site survey ahead of installation.', 'manual', 'completed');

-- #7 R32 Refrigerant Gas Cylinder - Item Request, still pending
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(7, 'item_request', 5, '2026-08-20', 'Juan Dela Cruz', 'Requested for a scheduled maintenance job - PO#2026-0892.', 'manual', 'pending');

-- #8 Copper Pipe Insulation Tape - Item Request, completed
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(8, 'item_request', 10, '2026-06-05', 'Ana Reyes', 'Requested for a copper line insulation job.', 'manual', 'completed');

-- #9 PVC Drain Pipe - Item Request, completed
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(9, 'item_request', 15, '2026-06-10', 'Maria Santos', 'Requested for condensate drainage on a new install.', 'manual', 'completed');

-- #10 AC Mounting Bracket Set - Borrowed for an emergency repair, then returned
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(10, 'borrow', 1, '2026-08-10', 'Roberto Cruz', 'Borrowed a spare bracket set for an emergency repair.', 'manual', 'completed'),
(10, 'return', 1, '2026-08-12', 'Roberto Cruz', 'Returned after the repair job.', 'manual', 'completed');

-- #11 Daikin Window FACQ10 - Item Request, completed
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(11, 'item_request', 1, '2026-06-25', 'Ana Reyes', 'Requested for a client delivery.', 'manual', 'completed');
