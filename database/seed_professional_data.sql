-- Run this in phpMyAdmin's SQL tab on your mister_aircon database, AFTER
-- every migration in database/ has been applied (this script assumes the
-- full current schema already exists: item_categories/brands/item_types
-- stripped to ID + Name, inventory_items with `model` as its required
-- display name, item_stock tracking quantity per (item, location) pair,
-- technical specs required only for the Asset item type, and Stock
-- In/Out logged through the transactions table).
--
-- WARNING: this WIPES every row in item_categories, brands, item_types,
-- locations, inventory_items, item_stock, transactions, and reports, and
-- resets their auto-increment counters back to 1. Use this on a fresh
-- dev/demo database, not on one with real data you want to keep.
--
-- What it seeds:
--   5  categories      (4 AC unit types from the ERD + 1 consumables/parts bucket)
--   6  brands          (real manufacturers, name only - no brand_code anymore)
--   2  item types      (Asset, Consumable - same as the ERD example values)
--   2  locations       (Main Store, Warehouse - same as the ERD example values)
--   11 inventory items (7 Asset AC units with full technical specs, 4
--                       Consumables with none) - each identified by
--                       `model` alone, since Item has no separate name
--                       field anymore
--   12 item_stock rows (per-item, per-location quantities - item #11 is
--                       fully stocked out, a real zero-quantity row)
--   30 transactions    (Stock In/Out history that produces the item_stock
--                       quantities above, plus Item Request / Borrow /
--                       Return activity log entries - a mix of completed,
--                       one pending, and one declined Item Request, so
--                       every status has a real example)

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE transactions;
TRUNCATE TABLE item_stock;
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
-- linked to items below via item_stock.
-- ============================================================
INSERT INTO locations (location_name) VALUES ('Main Store'), ('Warehouse');

-- ============================================================
-- Inventory Items (item_id 1-11)
-- Each row is: category_id, brand_id, item_type_id, model, energy_rating,
-- monthly_consumption, cooling_capacity, refrigerant, installation_type,
-- power_input, year. Consumables (#7-10) have no brand or AC specs, so
-- their `model` is a short part code instead of a manufacturer model
-- number. Every Asset item (#1-6, #11) has all 7 technical specs filled
-- in, since the app requires them for Asset; Consumables (#7-10) have
-- none, since the app hides and blanks that section for Consumable/no
-- item type.
-- ============================================================

-- #1 - Daikin Split Type
INSERT INTO inventory_items
    (category_id, brand_id, item_type_id, model, energy_rating, monthly_consumption, cooling_capacity, refrigerant, installation_type, power_input, year)
VALUES
    (1, 1, 1, 'FTKC25XVM', '5 Star (Inverter)', 45.50, '9,000 BTU/hr', 'R32', 'Wall Mounted', '220-240V, 50Hz, 1.5A', 2024);

-- #2 - Carrier Window Type
INSERT INTO inventory_items
    (category_id, brand_id, item_type_id, model, energy_rating, monthly_consumption, cooling_capacity, refrigerant, installation_type, power_input, year)
VALUES
    (2, 2, 1, '51QAC12', '3 Star', 68.00, '12,000 BTU/hr', 'R410A', 'Window Mounted', '220-240V, 50Hz, 5.8A', 2023);

-- #3 - Panasonic Floor Mounted
INSERT INTO inventory_items
    (category_id, brand_id, item_type_id, model, energy_rating, monthly_consumption, cooling_capacity, refrigerant, installation_type, power_input, year)
VALUES
    (3, 3, 1, 'S-24PU1U5B', '4 Star', 95.20, '24,000 BTU/hr', 'R32', 'Floor Standing', '220-240V, 50Hz, 9.5A', 2024);

-- #4 - LG Cassette Type
INSERT INTO inventory_items
    (category_id, brand_id, item_type_id, model, energy_rating, monthly_consumption, cooling_capacity, refrigerant, installation_type, power_input, year)
VALUES
    (4, 4, 1, 'ATNQ36GPLE0', '3 Star', 145.00, '36,000 BTU/hr', 'R410A', 'Ceiling Cassette', '380-415V, 3-Phase, 50Hz', 2022);

-- #5 - Samsung Split Type
INSERT INTO inventory_items
    (category_id, brand_id, item_type_id, model, energy_rating, monthly_consumption, cooling_capacity, refrigerant, installation_type, power_input, year)
VALUES
    (1, 5, 1, 'AR13AYHZAWK', '5 Star', 58.30, '13,000 BTU/hr', 'R32', 'Wall Mounted', '220-240V, 50Hz, 2.3A', 2024);

-- #6 - Mitsubishi Electric Split Type
INSERT INTO inventory_items
    (category_id, brand_id, item_type_id, model, energy_rating, monthly_consumption, cooling_capacity, refrigerant, installation_type, power_input, year)
VALUES
    (1, 6, 1, 'MSY-GL25VF', '5 Star (Inverter)', 82.00, '25,000 BTU/hr', 'R32', 'Wall Mounted', '220-240V, 50Hz, 3.8A', 2023);

-- #7 - R32 Refrigerant Gas Cylinder (Consumable, no brand, no AC specs)
INSERT INTO inventory_items
    (category_id, brand_id, item_type_id, model)
VALUES
    (5, NULL, 2, 'R32-CYL-10KG');

-- #8 - Copper Pipe Insulation Tape (Consumable)
INSERT INTO inventory_items
    (category_id, brand_id, item_type_id, model)
VALUES
    (5, NULL, 2, 'INSUL-TAPE-CU');

-- #9 - PVC Drain Pipe (Consumable)
INSERT INTO inventory_items
    (category_id, brand_id, item_type_id, model)
VALUES
    (5, NULL, 2, 'PVC-DRAIN-12');

-- #10 - AC Mounting Bracket Set (Consumable)
INSERT INTO inventory_items
    (category_id, brand_id, item_type_id, model)
VALUES
    (5, NULL, 2, 'BRKT-WALL-SET');

-- #11 - Daikin Window Type
INSERT INTO inventory_items
    (category_id, brand_id, item_type_id, model, energy_rating, monthly_consumption, cooling_capacity, refrigerant, installation_type, power_input, year)
VALUES
    (2, 1, 1, 'FACQ10', '3 Star', 52.00, '10,000 BTU/hr', 'R32', 'Window Mounted', '220-240V, 50Hz, 4.5A', 2022);

-- ============================================================
-- Item Stock (item_id, location_id 1=Main Store, 2=Warehouse)
-- Matches the Stock In/Out history below. #11 is fully stocked out (a
-- real zero-quantity row, same as what Stock Out leaves behind) so
-- there's an example of a depleted product.
-- ============================================================
INSERT INTO item_stock (item_id, location_id, quantity) VALUES
(1, 1, 8), (1, 2, 2),
(2, 2, 6),
(3, 1, 4),
(4, 2, 2),
(5, 1, 12),
(6, 2, 5),
(7, 1, 25),
(8, 1, 60),
(9, 2, 200),
(10, 2, 30),
(11, 1, 0);

-- ============================================================
-- Transactions - activity log for the seeded items
-- ============================================================

-- #1 Daikin Split FTKC25XVM - stocked in at both locations, then partly
-- stocked out (net: Main Store 8, Warehouse 2 - see item_stock above),
-- plus an Item Request
INSERT INTO transactions (item_id, location_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(1, 1, 'stock_in', 10, '2026-06-15', 'Roberto Cruz', 'Initial stock (seed data).', 'manual', 'completed'),
(1, 2, 'stock_in', 3, '2026-06-15', 'Roberto Cruz', 'Initial stock (seed data).', 'manual', 'completed'),
(1, 1, 'stock_out', 2, '2026-07-20', 'Roberto Cruz', 'Installed at client site - Barangay Lahug.', 'manual', 'completed'),
(1, 2, 'stock_out', 1, '2026-07-25', 'Ana Reyes', 'Released for a warehouse-side installation job.', 'manual', 'completed');
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(1, 'item_request', 2, '2026-07-20', 'Roberto Cruz', 'Requested for an installation job at Barangay Lahug.', 'manual', 'completed');

-- #2 Carrier Window 51QAC12 - stocked in, plus a declined Item Request
INSERT INTO transactions (item_id, location_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(2, 2, 'stock_in', 6, '2026-06-20', 'Maria Santos', 'Initial stock (seed data).', 'manual', 'completed');
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(2, 'item_request', 1, '2026-08-05', 'Maria Santos', 'Declined - insufficient documentation, needs supervisor approval.', 'manual', 'declined');

-- #3 Panasonic Floor S-24PU1U5B - stocked in, plus a Borrow/Return pair
INSERT INTO transactions (item_id, location_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(3, 1, 'stock_in', 4, '2026-07-01', 'Ana Reyes', 'Initial stock (seed data).', 'manual', 'completed');
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(3, 'borrow', 1, '2026-07-01', 'Ana Reyes', 'Borrowed for a trade show display unit.', 'manual', 'completed'),
(3, 'return', 1, '2026-07-08', 'Ana Reyes', 'Returned after the trade show.', 'manual', 'completed');

-- #4 LG Cassette ATNQ36GPLE0 - stocked in then partly out (net: Warehouse 2),
-- plus an Item Request
INSERT INTO transactions (item_id, location_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(4, 2, 'stock_in', 3, '2026-05-10', 'Roberto Cruz', 'Initial stock (seed data).', 'manual', 'completed'),
(4, 2, 'stock_out', 1, '2026-08-01', 'Roberto Cruz', 'Released for a commercial installation - conference room unit.', 'manual', 'completed');
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(4, 'item_request', 1, '2026-08-01', 'Roberto Cruz', 'Requested for a commercial installation - conference room unit.', 'manual', 'completed');

-- #5 Samsung Split AR13AYHZAWK - stocked in then partly out (net: Main
-- Store 12), plus an Item Request
INSERT INTO transactions (item_id, location_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(5, 1, 'stock_in', 15, '2026-06-01', 'Roberto Cruz', 'Initial stock (seed data).', 'manual', 'completed'),
(5, 1, 'stock_out', 3, '2026-07-10', 'Roberto Cruz', 'Released for a residential installation - client delivery.', 'manual', 'completed');
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(5, 'item_request', 1, '2026-07-10', 'Roberto Cruz', 'Requested for a residential installation - client delivery.', 'manual', 'completed');

-- #6 Mitsubishi Electric MSY-GL25VF - stocked in, plus a Borrow for a site survey
INSERT INTO transactions (item_id, location_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(6, 2, 'stock_in', 5, '2026-06-25', 'Juan Dela Cruz', 'Initial stock (seed data).', 'manual', 'completed');
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(6, 'borrow', 1, '2026-06-25', 'Juan Dela Cruz', 'Borrowed for a client site survey ahead of installation.', 'manual', 'completed');

-- #7 R32 Refrigerant Gas Cylinder - stocked in, plus a still-pending Item Request
INSERT INTO transactions (item_id, location_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(7, 1, 'stock_in', 25, '2026-06-05', 'Ana Reyes', 'Initial stock (seed data).', 'manual', 'completed');
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(7, 'item_request', 5, '2026-08-20', 'Juan Dela Cruz', 'Requested for a scheduled maintenance job - PO#2026-0892.', 'manual', 'pending');

-- #8 Copper Pipe Insulation Tape - stocked in, plus an Item Request
INSERT INTO transactions (item_id, location_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(8, 1, 'stock_in', 60, '2026-06-05', 'Ana Reyes', 'Initial stock (seed data).', 'manual', 'completed');
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(8, 'item_request', 10, '2026-06-05', 'Ana Reyes', 'Requested for a copper line insulation job.', 'manual', 'completed');

-- #9 PVC Drain Pipe - stocked in, plus an Item Request
INSERT INTO transactions (item_id, location_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(9, 2, 'stock_in', 200, '2026-06-05', 'Maria Santos', 'Initial stock (seed data).', 'manual', 'completed');
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(9, 'item_request', 15, '2026-06-10', 'Maria Santos', 'Requested for condensate drainage on a new install.', 'manual', 'completed');

-- #10 AC Mounting Bracket Set - stocked in, plus a Borrow/Return pair
INSERT INTO transactions (item_id, location_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(10, 2, 'stock_in', 30, '2026-06-05', 'Roberto Cruz', 'Initial stock (seed data).', 'manual', 'completed');
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(10, 'borrow', 1, '2026-08-10', 'Roberto Cruz', 'Borrowed a spare bracket set for an emergency repair.', 'manual', 'completed'),
(10, 'return', 1, '2026-08-12', 'Roberto Cruz', 'Returned after the repair job.', 'manual', 'completed');

-- #11 Daikin Window FACQ10 - stocked in, then all of it stocked back out
-- (net: 0 - see item_stock above), plus an Item Request
INSERT INTO transactions (item_id, location_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(11, 1, 'stock_in', 3, '2026-04-15', 'Ana Reyes', 'Initial stock (seed data).', 'manual', 'completed'),
(11, 1, 'stock_out', 3, '2026-06-25', 'Ana Reyes', 'Last unit sold - awaiting restock.', 'manual', 'completed');
INSERT INTO transactions (item_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(11, 'item_request', 1, '2026-06-25', 'Ana Reyes', 'Requested for a client delivery.', 'manual', 'completed');
