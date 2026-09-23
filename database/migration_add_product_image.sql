-- migration_add_product_image.sql
-- Adds an optional product photo column to inventory_items so
-- admin/warehouse staff/technicians can visually identify a product.
-- Never required - existing rows simply have image_path = NULL.
-- Run this once against an already-deployed database; a fresh install
-- created from coolstock_full_setup.sql already has this column.

ALTER TABLE inventory_items
    ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER year;
