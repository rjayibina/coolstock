-- Run this in phpMyAdmin's SQL tab, inside the mister_aircon database.
-- Create the database first if it doesn't exist yet:
-- CREATE DATABASE IF NOT EXISTS mister_aircon;
-- USE mister_aircon;

CREATE TABLE item_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    category_description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE inventory_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    description TEXT,
    unit_of_measure VARCHAR(50),
    quantity_on_hand INT DEFAULT 0,
    minimum_stock_level INT DEFAULT 0,
    serial_number VARCHAR(100) DEFAULT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (category_id) REFERENCES item_categories(category_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

-- Transactions: covers Stock-In, Stock-Out, Item Request, Borrow, and Return
-- (Item Request and Return Monitoring Module / Inventory Management Module)
CREATE TABLE transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    transaction_type ENUM('stock_in', 'stock_out', 'item_request', 'borrow', 'return') NOT NULL,
    quantity INT NOT NULL,
    technician_name VARCHAR(100) DEFAULT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(item_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

-- Reports: metadata for the Reporting and Monitoring Module. Report
-- *content* (stock summaries, usage reports) is computed live from
-- inventory_items/transactions - this table just logs that a report
-- was generated, by whom, and for what period, for monitoring/audit
-- purposes. No UI is built against this table yet.
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
-- MIGRATION ONLY: run these two lines instead of the CREATE TABLE
-- statements above if your database already has item_categories
-- and inventory_items from an earlier version of this schema.
-- ============================================================
-- ALTER TABLE inventory_items ADD COLUMN image_path VARCHAR(255) DEFAULT NULL;
-- (then run the CREATE TABLE transactions and CREATE TABLE reports statements above if you haven't already)
