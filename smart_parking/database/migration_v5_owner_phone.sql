-- =====================================================================
-- Migration v5: owner_phone on vehicles (walk-in contact number) +
-- feature support columns for SMS/loyalty/overstay (no other columns
-- needed — loyalty and overstay are computed on the fly from existing
-- tables, see includes/functions.php).
-- =====================================================================
-- ⚠ SUPERSEDED: database/schema.sql already creates vehicles with this
-- column. If you imported schema.sql fresh, skip this file.
-- Safe to run more than once — checks first, skips if already present.
-- =====================================================================
USE smart_parking_db;

SET @dbname = DATABASE();
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'vehicles' AND COLUMN_NAME = 'owner_phone') = 0,
    "ALTER TABLE vehicles ADD COLUMN owner_phone VARCHAR(20) NULL COMMENT 'Contact number for walk-in vehicles with no registered user account' AFTER color",
    "SELECT 'vehicles.owner_phone already exists — skipped'"
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
