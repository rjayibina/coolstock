-- Run this in phpMyAdmin's SQL tab on your mister_aircon database.
--
-- Phase 1 of the ERD expansion: three new lookup tables. None of these are
-- linked to inventory_items yet - that wiring (BrandID, ItemTypeID,
-- LocationID as foreign keys on the product record) happens in Phases 2-4.
-- This migration only creates the tables and their management screens.

CREATE TABLE brands (
    brand_id INT AUTO_INCREMENT PRIMARY KEY,
    brand_name VARCHAR(100) NOT NULL,
    -- The manufacturer's own code for this brand (e.g. Carrier -> "088"),
    -- not a per-item code - it belongs to the brand, not the product.
    brand_code VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE item_types (
    item_type_id INT AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE locations (
    location_id INT AUTO_INCREMENT PRIMARY KEY,
    location_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed Item Type and Location with the example values from the ERD sketch.
-- Brands aren't seeded - add your actual brands (Carrier, Daikin, etc.)
-- from the new Brands page, since the code varies per brand.
INSERT INTO item_types (type_name) VALUES ('Asset'), ('Consumable');
INSERT INTO locations (location_name) VALUES ('Main Store'), ('Warehouse');

-- Categories (Split Type, Window Type, Floor Standing, Cassette Type) go
-- into the EXISTING item_categories table via the Categories page you
-- already have - no new table needed for those.
