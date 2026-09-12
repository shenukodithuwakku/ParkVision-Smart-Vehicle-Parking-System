-- =====================================================================
-- Migration v4: One persistent QR per number plate + WhatsApp sharing
-- Run this once against an EXISTING smart_parking_db database that was
-- created before this feature was added.
-- If you are importing database/schema.sql fresh, skip this file — the
-- vehicles.qr_id column and its FK are already included there.
-- Safe to run more than once — it checks first and skips if the column
-- (and its "Duplicate column name 'qr_id'" error) already exists.
-- =====================================================================
USE smart_parking_db;

-- Each vehicle (number plate) gets exactly ONE QR code that is created
-- the first time it enters and reused on every future entry.
SET @dbname = DATABASE();
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'vehicles' AND COLUMN_NAME = 'qr_id') = 0,
    "ALTER TABLE vehicles
        ADD COLUMN qr_id INT UNSIGNED NULL AFTER color,
        ADD CONSTRAINT fk_vehicles_qr
            FOREIGN KEY (qr_id) REFERENCES qr_codes(qr_id)
            ON DELETE SET NULL ON UPDATE CASCADE,
        ADD INDEX idx_vehicles_qr (qr_id)",
    "SELECT 'vehicles.qr_id already exists — skipped'"
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill: if a plate already has a QR from a previous entry/reservation,
-- link it so we don't issue a duplicate one the next time it enters.
UPDATE vehicles v
JOIN (
    SELECT vehicle_id, MIN(qr_id) AS qr_id
    FROM (
        SELECT vehicle_id, qr_id FROM parking_records WHERE qr_id IS NOT NULL
        UNION ALL
        SELECT vehicle_id, qr_id FROM reservations WHERE qr_id IS NOT NULL
    ) x
    GROUP BY vehicle_id
) existing ON existing.vehicle_id = v.vehicle_id
SET v.qr_id = existing.qr_id
WHERE v.qr_id IS NULL;
