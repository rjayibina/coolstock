-- Run this in phpMyAdmin's SQL tab on your mister_aircon database.
-- Adds a `status` column so Item Request transactions can sit as
-- 'pending' (no stock deducted yet) until approved, at which point
-- they become 'completed' and the stock is actually deducted.
-- Every other transaction type is created as 'completed' immediately,
-- same as before.

ALTER TABLE transactions ADD COLUMN status ENUM('pending', 'completed') NOT NULL DEFAULT 'completed' AFTER source;
