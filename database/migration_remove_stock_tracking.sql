-- Run this in phpMyAdmin's SQL tab on your mister_aircon database.
--
-- Phase 2 of the strict-ERD-compliance rework: removes stock-level
-- tracking entirely. There is no replacement stock concept - not even
-- one computed from Transactions.
--
-- Removed entirely (not just hidden in the UI):
--   inventory_items.quantity_on_hand    - Products no longer shows a
--                                          Stock column, Stock Status
--                                          filter, or In/Low/Out badge.
--   inventory_items.minimum_stock_level
--   transactions.serial_number          - this only ever existed to
--                                          capture a serial number on
--                                          Stock Out, which is gone along
--                                          with the rest of Stock In/Out.
--
-- Consequence: Stock In, Stock Out, and bulk Stock In (buttons, modals,
-- and their controller/model code) are gone from Products. The
-- Dashboard's Low Stock Alerts stat and Low Stock Products table are
-- gone too. Item Request, Borrow, and Return stay as plain activity-log
-- entries on the Transactions page - they no longer adjust any product's
-- stock level (there isn't one to adjust), and approving an Item Request
-- no longer checks or deducts stock, it just marks it completed.

ALTER TABLE inventory_items
    DROP COLUMN quantity_on_hand,
    DROP COLUMN minimum_stock_level;

ALTER TABLE transactions
    DROP COLUMN serial_number;
