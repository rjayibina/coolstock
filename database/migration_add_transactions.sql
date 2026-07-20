-- Run this in phpMyAdmin's SQL tab on your mister_aircon database.
-- This creates the `transactions` table that's currently missing
-- (that's the cause of the "Table 'mister_aircon.transactions' doesn't
-- exist" error). Safe to run even if other tables already exist.

CREATE TABLE IF NOT EXISTS transactions (
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
