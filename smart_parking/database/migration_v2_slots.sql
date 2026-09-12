-- ================================================================
-- ParkVision — Migration v2: Sub-slot & Vehicle-Type Matching
-- ================================================================
-- ⚠ SUPERSEDED: database/schema.sql already creates parking_slots
-- and parking_records with every column below built in. If you
-- imported schema.sql (fresh or the one that ships with this
-- project now), you do NOT need to run this file — skip it.
--
-- Only run this against a genuinely OLD database that predates
-- vehicle_types/capacity/occupied_count/sub_slot and does not yet
-- have them. It's written to work on older MySQL/MariaDB versions
-- that don't support "ADD COLUMN IF NOT EXISTS" (that syntax needs
-- MySQL 8.0.29+, which is why it failed with a syntax error on
-- older servers such as the ones bundled with WAMP/XAMPP).
-- ================================================================
USE smart_parking_db;

-- 1. slot_type: normalise the ENUM (safe to run even if already correct)
ALTER TABLE parking_slots
  MODIFY COLUMN slot_type ENUM('car','bike','van') NOT NULL DEFAULT 'car';

-- 2. Add columns to parking_slots only if each is missing
SET @dbname = DATABASE();

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'parking_slots' AND COLUMN_NAME = 'vehicle_types') = 0,
  "ALTER TABLE parking_slots ADD COLUMN vehicle_types VARCHAR(50) NOT NULL DEFAULT 'car' AFTER slot_type",
  "SELECT 'vehicle_types already exists — skipped'"
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'parking_slots' AND COLUMN_NAME = 'capacity') = 0,
  "ALTER TABLE parking_slots ADD COLUMN capacity TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER vehicle_types",
  "SELECT 'capacity already exists — skipped'"
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'parking_slots' AND COLUMN_NAME = 'occupied_count') = 0,
  "ALTER TABLE parking_slots ADD COLUMN occupied_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER capacity",
  "SELECT 'occupied_count already exists — skipped'"
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Add sub_slot to parking_records only if missing
SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'parking_records' AND COLUMN_NAME = 'sub_slot') = 0,
  "ALTER TABLE parking_records ADD COLUMN sub_slot CHAR(1) NULL DEFAULT NULL AFTER qr_id",
  "SELECT 'sub_slot already exists — skipped'"
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. Backfill data
UPDATE parking_slots SET vehicle_types = slot_type WHERE vehicle_types = '' OR vehicle_types IS NULL;
UPDATE parking_slots SET capacity = 10 WHERE slot_type = 'bike';
UPDATE parking_slots SET slot_type = 'car', vehicle_types = 'car' WHERE slot_type IN ('ev','suv','handicap');

-- Verify
SELECT slot_type, vehicle_types, capacity, COUNT(*) AS cnt
FROM parking_slots
GROUP BY slot_type, vehicle_types, capacity
ORDER BY slot_type;
