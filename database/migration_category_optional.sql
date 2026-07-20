-- Run this in phpMyAdmin's SQL tab on your mister_aircon database.
-- Makes category_id optional on inventory_items so a product can
-- exist without being assigned to a category.

ALTER TABLE inventory_items MODIFY COLUMN category_id INT DEFAULT NULL;
