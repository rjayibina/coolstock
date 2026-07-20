-- Run this in phpMyAdmin's SQL tab on your mister_aircon database.
-- Safe to run even if you already applied the previous migration
-- (image_path column + transactions table) — this only adds the
-- new `reports` table for the Reporting and Monitoring Module.

CREATE TABLE IF NOT EXISTS reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    report_type ENUM('stock_summary', 'usage_report', 'low_stock', 'transaction_log') NOT NULL,
    date_from DATE DEFAULT NULL,
    date_to DATE DEFAULT NULL,
    generated_by VARCHAR(100) DEFAULT NULL,
    notes TEXT,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
