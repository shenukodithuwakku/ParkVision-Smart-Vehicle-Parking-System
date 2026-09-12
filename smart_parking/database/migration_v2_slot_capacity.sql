-- =====================================================================
-- ParkVision — Migration v2: Slot Capacity + Vehicle Type Matching
-- =====================================================================
-- ⚠ SUPERSEDED: database/schema.sql already creates parking_slots and
-- parking_records with every column added below. If you imported
-- schema.sql (fresh, or the version that ships with this project now),
-- you do NOT need to run this file — skip it entirely.
--
-- Only useful against a genuinely OLD database that predates
-- capacity/occupied_count/vehicle_types/sub_slot. Written to work on
-- older MySQL/MariaDB versions that don't support
-- "ADD COLUMN IF NOT EXISTS" (needs MySQL 8.0.29+), which is why the
-- original version of this file threw a syntax error on older servers
-- such as the ones bundled with WAMP/XAMPP.
-- =====================================================================
USE smart_parking_db;

SET @dbname = DATABASE();

-- ── 1. Normalise slot_type ENUM (safe even if already correct) ──
ALTER TABLE parking_slots
  MODIFY COLUMN slot_type ENUM('car','bike','van') NOT NULL DEFAULT 'car';

-- ── 2. Add columns to parking_slots only if each is missing ──
SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'parking_slots' AND COLUMN_NAME = 'capacity') = 0,
  "ALTER TABLE parking_slots ADD COLUMN capacity TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Max vehicles per slot (bikes can share)'",
  "SELECT 'capacity already exists — skipped'"
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'parking_slots' AND COLUMN_NAME = 'occupied_count') = 0,
  "ALTER TABLE parking_slots ADD COLUMN occupied_count TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Current vehicles parked'",
  "SELECT 'occupied_count already exists — skipped'"
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'parking_slots' AND COLUMN_NAME = 'vehicle_types') = 0,
  "ALTER TABLE parking_slots ADD COLUMN vehicle_types VARCHAR(50) NOT NULL DEFAULT 'car' COMMENT 'CSV of allowed types: car | bike | van'",
  "SELECT 'vehicle_types already exists — skipped'"
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 3. Add sub_slot to parking_records only if missing ──
SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'parking_records' AND COLUMN_NAME = 'sub_slot') = 0,
  "ALTER TABLE parking_records ADD COLUMN sub_slot CHAR(1) NULL DEFAULT NULL COMMENT 'A/B/C... letter within a shared slot (bikes)'",
  "SELECT 'sub_slot already exists — skipped'"
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 4. Backfill vehicle_types to match slot_type ──
UPDATE parking_slots SET vehicle_types = 'bike' WHERE slot_type = 'bike';
UPDATE parking_slots SET vehicle_types = 'car'  WHERE slot_type = 'car';
UPDATE parking_slots SET vehicle_types = 'van'  WHERE slot_type = 'van';

-- ── 5. Capacity: 1 for car/van, 10 for bike ──
UPDATE parking_slots SET capacity = 1  WHERE slot_type IN ('car','van');
UPDATE parking_slots SET capacity = 10 WHERE slot_type = 'bike';

-- ── 6. Fix occupied_count to match reality ──
UPDATE parking_slots s
  SET s.occupied_count = (
    SELECT COUNT(*) FROM parking_records pr
    WHERE pr.slot_id = s.slot_id AND pr.status = 'in_progress'
  );

-- ── 7. Fix status based on occupied_count ──
UPDATE parking_slots
  SET status = 'available'
  WHERE occupied_count = 0 AND status != 'maintenance';

UPDATE parking_slots
  SET status = 'occupied'
  WHERE occupied_count >= capacity;

-- ── 8. Verify result ──
SELECT slot_code, slot_type, vehicle_types, capacity, occupied_count, status
FROM parking_slots
ORDER BY slot_code
LIMIT 30;
