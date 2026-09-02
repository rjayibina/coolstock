-- Run this in phpMyAdmin's SQL tab on your mister_aircon database.
--
-- Reintroduces stock-quantity tracking, but per location this time - an
-- item's stock is no longer a single number (or a single location), it's
-- a quantity per (item, location) pair. A product with no stock recorded
-- anywhere simply has no rows here yet.
--
-- This replaces inventory_items.location_id (a single location per
-- product) entirely - that concept no longer fits once one product can
-- have stock spread across multiple locations. Location association now
-- comes only from item_stock: an item "is at" whichever locations have a
-- stock row for it.
--
-- Stock In / Stock Out come back as transaction types, logged through
-- the existing transactions table (not a separate ledger) - each row
-- also records which location the movement happened at.

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

ALTER TABLE inventory_items
    DROP FOREIGN KEY fk_inventory_items_location,
    DROP COLUMN location_id;

ALTER TABLE transactions
    MODIFY COLUMN transaction_type ENUM('item_request', 'borrow', 'return', 'stock_in', 'stock_out') NOT NULL,
    ADD COLUMN location_id INT NULL AFTER item_id,
    ADD CONSTRAINT fk_transactions_location
        FOREIGN KEY (location_id) REFERENCES locations(location_id)
        ON DELETE RESTRICT;
