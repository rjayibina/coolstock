-- Run this in phpMyAdmin's SQL tab on your mister_aircon database.
--
-- Previously, transactions.item_id was NOT NULL with an ON DELETE RESTRICT
-- foreign key, so deleting a product either had to be blocked, or (in an
-- earlier revision) had to delete the product's transaction rows first.
-- Since the Transactions/Logs module is meant to be a permanent audit
-- trail, we don't want either of those: this migration makes item_id
-- nullable and switches the foreign key to ON DELETE SET NULL, so
-- deleting a product just detaches its past transactions (item_name will
-- read as NULL / "Unknown product" for those rows) instead of removing
-- them or blocking the delete.
--
-- The DROP FOREIGN KEY step looks up the constraint name dynamically
-- instead of hardcoding it, since MySQL auto-generates that name and it
-- can differ between installs.

SET @fk_name = (
    SELECT CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'transactions'
      AND COLUMN_NAME = 'item_id'
      AND REFERENCED_TABLE_NAME = 'inventory_items'
    LIMIT 1
);

SET @drop_sql = CONCAT('ALTER TABLE transactions DROP FOREIGN KEY ', @fk_name);
PREPARE stmt FROM @drop_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE transactions MODIFY item_id INT NULL;

ALTER TABLE transactions
    ADD CONSTRAINT fk_transactions_item_id
    FOREIGN KEY (item_id) REFERENCES inventory_items(item_id)
    ON UPDATE CASCADE
    ON DELETE SET NULL;
