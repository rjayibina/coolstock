-- ============================================================
-- CoolStock / Mister Aircon Inventory System
-- FULL SETUP: complete current schema (strict ERD compliance, per-location
-- Stock In/Out, Delivery/Transfer with order/transfer numbers, Item Type
-- serial-number requirement, Stock Out serial numbers) + professional
-- demo seed data.
-- ============================================================
-- Use this on a fresh/empty database - it replaces running every
-- individual migration file in database/ one by one. Run it top to
-- bottom in phpMyAdmin's SQL tab.
--
-- If the database doesn't exist yet, uncomment these two lines first:
-- CREATE DATABASE IF NOT EXISTS mister_aircon;
-- USE mister_aircon;
--
-- This script assumes the database is EMPTY (no tables). If you still
-- have old tables lying around, drop them first:
-- DROP TABLE IF EXISTS item_stock, transactions, reports, inventory_items, brands, item_types, locations, item_categories;

-- ============================================================
-- SCHEMA
-- ============================================================

-- Lookup tables: strictly ID + Name, matching the hand-drawn ERD.
CREATE TABLE item_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL
);

CREATE TABLE brands (
    brand_id INT AUTO_INCREMENT PRIMARY KEY,
    brand_name VARCHAR(100) NOT NULL
);

-- type_name (e.g. Asset, Consumable) plus a requires_serial flag: whether
-- Stock Out on a product of this item type must capture a serial number
-- per unit. Defaults to 1 (required) - the "safer default" this project
-- uses throughout; a product with NO item type assigned is also treated
-- as requiring a serial number (see inventory_items query notes below).
CREATE TABLE item_types (
    item_type_id INT AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(100) NOT NULL,
    requires_serial TINYINT(1) NOT NULL DEFAULT 1
);

-- Lookup table. No longer a single location_id on inventory_items (that
-- doesn't fit once one product can have stock split across locations) -
-- linked to products via item_stock below instead. Its own page still
-- supports Add/Edit/Delete, but stays a simple ID + Name list (no
-- Products column/filter/sort).
CREATE TABLE locations (
    location_id INT AUTO_INCREMENT PRIMARY KEY,
    location_name VARCHAR(100) NOT NULL
);

-- Item: strictly the ERD's tblItem fields. There is no name field of its
-- own - `model` is the item's only identifying field, so it's required
-- and used everywhere a display name is needed (table, search, sort,
-- CSV, the Transactions item picker). No stock-quantity column here -
-- an item's quantity and location(s) come from item_stock below.
CREATE TABLE inventory_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT DEFAULT NULL,
    brand_id INT NULL,
    item_type_id INT NULL,
    model VARCHAR(100) NOT NULL,
    -- AC technical specs. Required (enforced in the app) when
    -- item_type_id is the "Asset" item type; always null for Consumable
    -- or when no item type is set - nullable here since the DB can't
    -- express that conditional itself.
    energy_rating VARCHAR(20) DEFAULT NULL,
    monthly_consumption DECIMAL(10,2) DEFAULT NULL,
    cooling_capacity VARCHAR(50) DEFAULT NULL,
    refrigerant VARCHAR(50) DEFAULT NULL,
    installation_type VARCHAR(50) DEFAULT NULL,
    power_input VARCHAR(50) DEFAULT NULL,
    year INT DEFAULT NULL,
    FOREIGN KEY (category_id) REFERENCES item_categories(category_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_items_brand
        FOREIGN KEY (brand_id) REFERENCES brands(brand_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_items_item_type
        FOREIGN KEY (item_type_id) REFERENCES item_types(item_type_id)
        ON DELETE RESTRICT
);

-- One row per (item, location) pair holding that item's quantity at that
-- location. An item with no stock recorded anywhere simply has no rows
-- here - a row is created on demand by the first Stock In/Delivery for
-- that item+location pair. A product's overall quantity is the sum of
-- its rows here.
CREATE TABLE item_stock (
    item_id INT NOT NULL,
    location_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    PRIMARY KEY (item_id, location_id),
    CONSTRAINT fk_item_stock_item
        FOREIGN KEY (item_id) REFERENCES inventory_items(item_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_item_stock_location
        FOREIGN KEY (location_id) REFERENCES locations(location_id)
        ON DELETE RESTRICT
);

-- Transactions: activity-log entries for the Item Request and Return
-- Monitoring Module (Item Request, Borrow, Return) plus Stock In/Out,
-- Delivery, and Transfer for the Inventory Management Module ("Product
-- Movement" in the sidebar). Only Stock In/Out/Delivery/Transfer move
-- stock - each is paired with an item_stock update by the app when it's
-- created; Item Request/Borrow/Return never touch quantity. location_id
-- is set for all four stock-moving types (the FROM location for a
-- Transfer); to_location_id is set only for a Transfer (the TO location).
-- supplier_name is set only for a Delivery. item_id is nullable with ON
-- DELETE SET NULL so this stays a permanent audit trail even after a
-- product is deleted (its model reads as "Unknown product" in the UI for
-- those rows).
--
-- reference_number: an Order # ('DO-000001', ...) or Transfer #
-- ('TR-000001', ...) shared by every row written from one Delivery/
-- Transfer submission - powers the Product Movement page's "view every
-- product in this order/transfer" modal. NULL for every other type.
--
-- manually_added: true only for a Delivery line whose product didn't
-- exist in the catalog yet and was created on the spot via "Add Product
-- Manually" - powers the "New" badge on that same modal. False for
-- every other line, including a Delivery of an existing catalog product.
--
-- serial_number: which unit's serial number a Stock Out took out, for a
-- product whose item type requires one (see item_types.requires_serial
-- above). A multi-unit serialized Stock Out is logged as several
-- quantity=1 rows, one per serial, rather than packing several serials
-- into one row. NULL for Stock In, non-serialized Stock Out, and every
-- other transaction type.
CREATE TABLE transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NULL,
    location_id INT NULL,
    to_location_id INT NULL,
    transaction_type ENUM('item_request', 'borrow', 'return', 'stock_in', 'stock_out', 'delivery', 'transfer') NOT NULL,
    reference_number VARCHAR(20) DEFAULT NULL,
    manually_added TINYINT(1) NOT NULL DEFAULT 0,
    quantity INT NOT NULL,
    serial_number VARCHAR(100) DEFAULT NULL,
    -- The date the movement actually happened, separate from created_at
    -- (the audit timestamp of when the row was logged).
    transaction_date DATE DEFAULT NULL,
    -- Free-text for now. Doubles as "Received By" (Stock In/Delivery) /
    -- "Released By" (Stock Out) / "Moved By" (Transfer) in the UI - same
    -- column either way.
    technician_name VARCHAR(100) DEFAULT NULL,
    -- Free-text supplier name, Delivery only.
    supplier_name VARCHAR(150) DEFAULT NULL,
    notes TEXT,
    -- 'manual' = logged from the Transactions page or a product's Stock
    -- In/Out form. 'auto' is historical only - no code path sets it anymore.
    source ENUM('manual', 'auto') NOT NULL DEFAULT 'manual',
    -- 'pending' = an Item Request that hasn't been approved yet.
    -- 'completed' = everything else, and approved requests. 'declined' =
    -- a refused request, kept for the audit trail.
    status ENUM('pending', 'completed', 'declined') NOT NULL DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_transactions_item_id
        FOREIGN KEY (item_id) REFERENCES inventory_items(item_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_transactions_location
        FOREIGN KEY (location_id) REFERENCES locations(location_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_transactions_to_location
        FOREIGN KEY (to_location_id) REFERENCES locations(location_id)
        ON DELETE RESTRICT,
    INDEX idx_transactions_reference_number (reference_number)
);

-- Reports: metadata for the (not yet built) Reporting and Monitoring
-- Module. Report *content* would be computed live from inventory_items/
-- transactions - this table just logs that a report was generated, for
-- audit purposes. Untouched by the strict-ERD rework - no app code
-- reads or writes it yet.
CREATE TABLE reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    report_type ENUM('stock_summary', 'usage_report', 'low_stock', 'transaction_log') NOT NULL,
    date_from DATE DEFAULT NULL,
    date_to DATE DEFAULT NULL,
    generated_by VARCHAR(100) DEFAULT NULL,
    notes TEXT,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- SEED DATA (professional demo data)
-- ============================================================

-- ---- Categories (category_id 1-5) ----
INSERT INTO item_categories (category_name) VALUES
('Split Type AC'),
('Window Type AC'),
('Floor Mounted AC'),
('Cassette Type AC'),
('Consumables & Spare Parts');

-- ---- Brands (brand_id 1-6) ----
INSERT INTO brands (brand_name) VALUES
('Daikin'),
('Carrier'),
('Panasonic'),
('LG'),
('Samsung'),
('Mitsubishi Electric');

-- ---- Item Types (item_type_id 1-2) ----
-- Asset requires a serial number on Stock Out; Consumable does not.
INSERT INTO item_types (type_name, requires_serial) VALUES
('Asset', 1),
('Consumable', 0);

-- ---- Locations (location_id 1-2) - linked to items via item_stock ----
INSERT INTO locations (location_name) VALUES ('Main Store'), ('Warehouse');

-- ---- Inventory Items (item_id 1-11) ----
-- Consumables (#7-10) have no brand or AC specs, so their `model` is a
-- short part code instead of a manufacturer model number. Every Asset
-- item type (#1-6, #11) has all 7 technical specs filled in, since the
-- app requires them for Asset; Consumables (#7-10) have none, since the
-- app hides and blanks that section for Consumable/no item type.

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

-- #12 - LG Split Type (new - receives a Delivery, then a Transfer, then a
-- serialized Stock Out, to demonstrate all three current-session features)
INSERT INTO inventory_items
    (category_id, brand_id, item_type_id, model, energy_rating, monthly_consumption, cooling_capacity, refrigerant, installation_type, power_input, year)
VALUES
    (1, 4, 1, 'S4-Q09JA3AE', '5 Star (Inverter)', 48.00, '9,200 BTU/hr', 'R32', 'Wall Mounted', '220-240V, 50Hz, 1.6A', 2025);

-- #13 - Copper Pipe Coil (Consumable, new - delivered alongside #12 in the
-- same order, to demonstrate a Delivery covering more than one product)
INSERT INTO inventory_items
    (category_id, brand_id, item_type_id, model)
VALUES
    (5, NULL, 2, 'CU-PIPE-1-4IN');

-- ---- Item Stock (item_id, location_id 1=Main Store, 2=Warehouse) ----
-- Matches the Stock In/Out/Delivery/Transfer history below. #11 is fully
-- stocked out (a real zero-quantity row, same as what Stock Out leaves
-- behind) so there's an example of a depleted product.
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
(11, 1, 0),
-- #12: delivered 6 to Main Store, 2 transferred out to Warehouse, 1
-- serialized unit stocked out from Main Store -> Main Store 6-2-1=3,
-- Warehouse 0+2=2.
(12, 1, 3), (12, 2, 2),
-- #13: delivered 40 to Main Store, untouched since.
(13, 1, 40);

-- ---- Transactions - activity log for the seeded items ----
-- Item Request/Borrow/Return are intentionally not seeded - that workflow
-- is on pause for now (no UI creates or approves them currently). Every
-- item below only has Stock In/Out/Delivery/Transfer history.

-- #1 Daikin Split FTKC25XVM - stocked in at both locations, then partly
-- stocked out (net: Main Store 8, Warehouse 2 - see item_stock above)
INSERT INTO transactions (item_id, location_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(1, 1, 'stock_in', 10, '2026-06-15', 'Roberto Cruz', 'Initial stock (seed data).', 'manual', 'completed'),
(1, 2, 'stock_in', 3, '2026-06-15', 'Roberto Cruz', 'Initial stock (seed data).', 'manual', 'completed'),
(1, 1, 'stock_out', 2, '2026-07-20', 'Roberto Cruz', 'Installed at client site - Barangay Lahug.', 'manual', 'completed'),
(1, 2, 'stock_out', 1, '2026-07-25', 'Ana Reyes', 'Released for a warehouse-side installation job.', 'manual', 'completed');

-- #2 Carrier Window 51QAC12 - stocked in
INSERT INTO transactions (item_id, location_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(2, 2, 'stock_in', 6, '2026-06-20', 'Maria Santos', 'Initial stock (seed data).', 'manual', 'completed');

-- #3 Panasonic Floor S-24PU1U5B - stocked in
INSERT INTO transactions (item_id, location_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(3, 1, 'stock_in', 4, '2026-07-01', 'Ana Reyes', 'Initial stock (seed data).', 'manual', 'completed');

-- #4 LG Cassette ATNQ36GPLE0 - stocked in then partly out (net: Warehouse 2)
INSERT INTO transactions (item_id, location_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(4, 2, 'stock_in', 3, '2026-05-10', 'Roberto Cruz', 'Initial stock (seed data).', 'manual', 'completed'),
(4, 2, 'stock_out', 1, '2026-08-01', 'Roberto Cruz', 'Released for a commercial installation - conference room unit.', 'manual', 'completed');

-- #5 Samsung Split AR13AYHZAWK - stocked in then partly out (net: Main Store 12)
INSERT INTO transactions (item_id, location_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(5, 1, 'stock_in', 15, '2026-06-01', 'Roberto Cruz', 'Initial stock (seed data).', 'manual', 'completed'),
(5, 1, 'stock_out', 3, '2026-07-10', 'Roberto Cruz', 'Released for a residential installation - client delivery.', 'manual', 'completed');

-- #6 Mitsubishi Electric MSY-GL25VF - stocked in
INSERT INTO transactions (item_id, location_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(6, 2, 'stock_in', 5, '2026-06-25', 'Juan Dela Cruz', 'Initial stock (seed data).', 'manual', 'completed');

-- #7 R32 Refrigerant Gas Cylinder - stocked in
INSERT INTO transactions (item_id, location_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(7, 1, 'stock_in', 25, '2026-06-05', 'Ana Reyes', 'Initial stock (seed data).', 'manual', 'completed');

-- #8 Copper Pipe Insulation Tape - stocked in
INSERT INTO transactions (item_id, location_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(8, 1, 'stock_in', 60, '2026-06-05', 'Ana Reyes', 'Initial stock (seed data).', 'manual', 'completed');

-- #9 PVC Drain Pipe - stocked in
INSERT INTO transactions (item_id, location_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(9, 2, 'stock_in', 200, '2026-06-05', 'Maria Santos', 'Initial stock (seed data).', 'manual', 'completed');

-- #10 AC Mounting Bracket Set - stocked in
INSERT INTO transactions (item_id, location_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(10, 2, 'stock_in', 30, '2026-06-05', 'Roberto Cruz', 'Initial stock (seed data).', 'manual', 'completed');

-- #11 Daikin Window FACQ10 - stocked in, then all of it stocked back out
-- (net: 0 - see item_stock above)
INSERT INTO transactions (item_id, location_id, transaction_type, quantity, transaction_date, technician_name, notes, source, status) VALUES
(11, 1, 'stock_in', 3, '2026-04-15', 'Ana Reyes', 'Initial stock (seed data).', 'manual', 'completed'),
(11, 1, 'stock_out', 3, '2026-06-25', 'Ana Reyes', 'Last unit sold - awaiting restock.', 'manual', 'completed');

-- #12 + #13 - Delivery DO-000001 (both products, one order, one supplier),
-- then Transfer TR-000001 (#12 only, Main Store -> Warehouse), then a
-- serialized Stock Out on #12 (Asset item type, requires a serial number).
-- Demonstrates every feature built this session: Delivery/Transfer with
-- a shared reference_number (click it on Product Movement to see both
-- lines of the order together), and per-unit serial number tracking.
INSERT INTO transactions (item_id, location_id, transaction_type, reference_number, quantity, transaction_date, technician_name, supplier_name, notes, source, status) VALUES
(12, 1, 'delivery', 'DO-000001', 6, '2026-08-28', 'Ana Reyes', 'Aircon Parts Distribution Inc.', 'New LG split type units - Q3 restock order.', 'manual', 'completed'),
(13, 1, 'delivery', 'DO-000001', 40, '2026-08-28', 'Ana Reyes', 'Aircon Parts Distribution Inc.', 'Copper pipe coil, same order as the LG units.', 'manual', 'completed');

INSERT INTO transactions (item_id, location_id, to_location_id, transaction_type, reference_number, quantity, transaction_date, technician_name, notes, source, status) VALUES
(12, 1, 2, 'transfer', 'TR-000001', 2, '2026-08-30', 'Roberto Cruz', 'Moved 2 units to Warehouse ahead of a scheduled commercial job.', 'manual', 'completed');

INSERT INTO transactions (item_id, location_id, transaction_type, quantity, serial_number, transaction_date, technician_name, notes, source, status) VALUES
(12, 1, 'stock_out', 1, 'LGSN-20250912-0007', '2026-09-01', 'Roberto Cruz', 'Installed at client site - serial logged for warranty tracking.', 'manual', 'completed');
