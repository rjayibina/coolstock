-- ============================================================
-- CoolStock / Mister Aircon Inventory System
-- FULL SETUP: schema (current state - strict ERD compliance Phases 1-4,
-- plus Location re-linked to Products and Asset-required specs) + demo
-- seed data
-- ============================================================
-- Use this on a fresh/empty database - it replaces running all 17
-- individual migration files one by one. Run it top to bottom in
-- phpMyAdmin's SQL tab.
--
-- If the database doesn't exist yet, uncomment these two lines first:
-- CREATE DATABASE IF NOT EXISTS mister_aircon;
-- USE mister_aircon;
--
-- This script assumes the database is EMPTY (no tables). If you still
-- have old tables lying around, drop them first:
-- DROP TABLE IF EXISTS transactions, reports, inventory_items, brands, item_types, locations, item_categories;

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

CREATE TABLE item_types (
    item_type_id INT AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(100) NOT NULL
);

-- Lookup table, linked to inventory_items via location_id below (one
-- location per product). Its own page still supports Add/Edit/Delete,
-- but stays a simple ID + Name list (no Products column/filter/sort).
CREATE TABLE locations (
    location_id INT AUTO_INCREMENT PRIMARY KEY,
    location_name VARCHAR(100) NOT NULL
);

-- Item: the ERD's tblItem fields plus location_id. There is no name
-- field of its own - `model` is the item's only identifying field, so
-- it's required and used everywhere a display name is needed (table,
-- search, sort, CSV, the Transactions item picker). No stock-level
-- fields either - there is no stock concept in this system, not even
-- one computed from Transactions.
CREATE TABLE inventory_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT DEFAULT NULL,
    brand_id INT NULL,
    item_type_id INT NULL,
    location_id INT NULL,
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
        ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_items_location
        FOREIGN KEY (location_id) REFERENCES locations(location_id)
        ON DELETE RESTRICT
);

-- Transactions: plain activity-log entries for the Item Request and
-- Return Monitoring Module - Item Request, Borrow, or Return. Stock
-- In/Out and all stock-quantity math were removed entirely; a
-- transaction never adjusts any product's stock level, it just records
-- that a movement happened. item_id is nullable with ON DELETE SET NULL
-- so this stays a permanent audit trail even after a product is deleted
-- (its model reads as "Unknown product" in the UI for those rows).
CREATE TABLE transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NULL,
    transaction_type ENUM('item_request', 'borrow', 'return') NOT NULL,
    quantity INT NOT NULL,
    -- The date the movement actually happened, separate from created_at
    -- (the audit timestamp of when the row was logged).
    transaction_date DATE DEFAULT NULL,
    technician_name VARCHAR(100) DEFAULT NULL,
    notes TEXT,
    -- 'manual' = logged from the Transactions page. 'auto' only ever
    -- existed for system-generated Stock In/Out rows, which are gone -
    -- new rows are always 'manual'.
    source ENUM('manual', 'auto') NOT NULL DEFAULT 'manual',
    -- 'pending' = an Item Request that hasn't been approved yet.
    -- 'completed' = everything else, and approved requests. 'declined' =
    -- a refused request, kept for the audit trail.
    status ENUM('pending', 'completed', 'declined') NOT NULL DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_transactions_item_id
        FOREIGN KEY (item_id) REFERENCES inventory_items(item_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
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
INSERT INTO item_types (type_name) VALUES ('Asset'), ('Consumable');

-- ---- Locations (location_id 1-2) - linked to items via location_id ----
INSERT INTO locations (location_name) VALUES ('Main Store'), ('Warehouse');

-- ---- Inventory Items (item_id 1-11) ----
-- Consumables (#7-10) have no brand or AC specs, so their `model` is a
-- short part code instead of a manufacturer model number. Every Asset
-- item type (#1-6, #11) has all 7 technical specs filled in, since the
-- app requires them for Asset; Consumables (#7-10) have none, since the
-- app hides and blanks that section for Consumable/no item type.

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

-- ---- Transactions - activity log for the seeded items. No quantity
-- math - these are just records that a movement happened. ----

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
