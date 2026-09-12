-- =====================================================================
-- Smart Vehicle Parking Management System
-- Database: smart_parking_db
-- Engine: InnoDB (required for FK + transaction support)
-- Import this file directly into phpMyAdmin on WAMP.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS smart_parking_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smart_parking_db;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. ADMINS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS admins;
CREATE TABLE admins (
    admin_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50)  NOT NULL UNIQUE,
    email           VARCHAR(120) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    full_name       VARCHAR(120) NOT NULL,
    role            ENUM('super_admin','operator') NOT NULL DEFAULT 'operator',
    status          ENUM('active','disabled') NOT NULL DEFAULT 'active',
    last_login_at   DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. USERS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    user_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(120) NOT NULL,
    email           VARCHAR(120) NOT NULL UNIQUE,
    phone           VARCHAR(20)  NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    status          ENUM('active','blocked') NOT NULL DEFAULT 'active',
    remember_token  VARCHAR(255) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_email (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. VEHICLES
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS vehicles;
CREATE TABLE vehicles (
    vehicle_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NULL,
    plate_number    VARCHAR(20)  NOT NULL UNIQUE,
    vehicle_type    ENUM('car','bike','van','suv','ev') NOT NULL DEFAULT 'car',
    model           VARCHAR(80)  NULL,
    color           VARCHAR(40)  NULL,
    owner_phone     VARCHAR(20)  NULL COMMENT 'Contact number for walk-in vehicles with no registered user account',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_vehicles_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_vehicles_plate (plate_number)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. PARKING SLOTS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS parking_slots;
CREATE TABLE parking_slots (
    slot_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slot_code       VARCHAR(20) NOT NULL UNIQUE,          -- e.g. A-01, B-12
    zone            VARCHAR(40) NOT NULL DEFAULT 'General',
    slot_type       ENUM('car','bike','van') NOT NULL DEFAULT 'car',  -- 3 types only
    vehicle_types   VARCHAR(50) NOT NULL DEFAULT 'car',   -- CSV: car | bike | van
    capacity        TINYINT UNSIGNED NOT NULL DEFAULT 1,  -- max vehicles (bikes: 10)
    occupied_count  TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- current vehicles parked
    status          ENUM('available','reserved','occupied','maintenance') NOT NULL DEFAULT 'available',
    hourly_rate     DECIMAL(8,2) NOT NULL DEFAULT 100.00,
    notes           VARCHAR(255) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slots_status (status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. QR CODES  (created first so reservations / parking_records can reference it)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS qr_codes;
CREATE TABLE qr_codes (
    qr_id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code_value      VARCHAR(64) NOT NULL UNIQUE,      -- random token encoded inside the QR image
    qr_image_path   VARCHAR(255) NULL,
    is_used         TINYINT(1) NOT NULL DEFAULT 0,
    used_at         DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Each vehicle (number plate) gets exactly ONE QR code, created the first
-- time it enters/is booked and reused on every future entry — see
-- includes/qr.php::get_or_create_vehicle_qr().
ALTER TABLE vehicles
    ADD COLUMN qr_id INT UNSIGNED NULL AFTER owner_phone,
    ADD CONSTRAINT fk_vehicles_qr
        FOREIGN KEY (qr_id) REFERENCES qr_codes(qr_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    ADD INDEX idx_vehicles_qr (qr_id);

-- ---------------------------------------------------------------------
-- 6. RESERVATIONS
--    Conflict-proofing strategy (belt + suspenders):
--      a) PHP wraps the insert in a transaction with
--         "SELECT ... FOR UPDATE" on the slot row (row-level lock).
--      b) A generated column + UNIQUE INDEX makes it physically
--         impossible for two ACTIVE (pending/confirmed) reservations
--         to exist for the same slot/date/time, even under race
--         conditions or if a developer bypasses the PHP layer.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS reservations;
CREATE TABLE reservations (
    reservation_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id              INT UNSIGNED NOT NULL,
    slot_id              INT UNSIGNED NOT NULL,
    vehicle_id           INT UNSIGNED NULL,
    booking_date         DATE NOT NULL,
    arrival_time         TIME NOT NULL,
    grace_period_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    expires_at           DATETIME NOT NULL,            -- booking_date+arrival_time+grace_period, set by PHP
    status               ENUM('pending','confirmed','cancelled','expired','completed') NOT NULL DEFAULT 'confirmed',
    qr_id                INT UNSIGNED NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- generated column: NULL for inactive rows, otherwise a composite key.
    -- MySQL/MariaDB unique indexes allow unlimited NULLs but only ONE of
    -- any non-null value, so only one active+confirmed booking can exist
    -- per slot/date/time. Must be VIRTUAL, not STORED: MariaDB (error 1901)
    -- refuses a STORED generated column that references a column (slot_id)
    -- which also carries a FOREIGN KEY. VIRTUAL works identically here and
    -- InnoDB fully supports a UNIQUE secondary index on virtual columns.
    active_lock VARCHAR(60) GENERATED ALWAYS AS (
        CASE WHEN status IN ('pending','confirmed')
             THEN CONCAT(slot_id, '_', booking_date, '_', arrival_time)
             ELSE NULL END
    ) VIRTUAL,

    CONSTRAINT fk_res_user    FOREIGN KEY (user_id)  REFERENCES users(user_id)          ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_res_slot    FOREIGN KEY (slot_id)  REFERENCES parking_slots(slot_id)   ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_res_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id)   ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_res_qr      FOREIGN KEY (qr_id)     REFERENCES qr_codes(qr_id)         ON DELETE SET NULL ON UPDATE CASCADE,

    UNIQUE KEY uq_active_slot_datetime (active_lock),
    INDEX idx_res_status (status),
    INDEX idx_res_date (booking_date),
    INDEX idx_res_expires (expires_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. PARKING RECORDS (actual entry/exit log)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS parking_records;
CREATE TABLE parking_records (
    record_id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id       INT UNSIGNED NULL,
    vehicle_id            INT UNSIGNED NOT NULL,
    slot_id                INT UNSIGNED NOT NULL,
    qr_id                    INT UNSIGNED NULL,         -- QR shown to the driver, scanned again at exit
    plate_number_detected VARCHAR(20) NULL,           -- raw OCR output, kept even if vehicle lookup fails
    sub_slot               CHAR(1) NULL DEFAULT NULL,    -- A/B/C sub-slot letter (for shared slots like bikes)
    entry_time             DATETIME NOT NULL,
    exit_time               DATETIME NULL,
    duration_minutes        INT UNSIGNED NULL,
    fee_amount               DECIMAL(10,2) NULL,
    status                    ENUM('in_progress','completed') NOT NULL DEFAULT 'in_progress',
    created_at                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_pr_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_pr_vehicle     FOREIGN KEY (vehicle_id)     REFERENCES vehicles(vehicle_id)         ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pr_slot        FOREIGN KEY (slot_id)        REFERENCES parking_slots(slot_id)       ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pr_qr          FOREIGN KEY (qr_id)          REFERENCES qr_codes(qr_id)              ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_pr_status (status),
    INDEX idx_pr_entry (entry_time)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. PAYMENTS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS payments;
CREATE TABLE payments (
    payment_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    record_id          INT UNSIGNED NOT NULL,
    amount               DECIMAL(10,2) NOT NULL,
    payment_method        ENUM('cash','card','online') NOT NULL DEFAULT 'cash',
    payment_status         ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'paid',
    transaction_ref          VARCHAR(80) NULL,
    paid_at                   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_pay_record FOREIGN KEY (record_id) REFERENCES parking_records(record_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_pay_status (payment_status),
    INDEX idx_pay_date (paid_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. ACTIVITY LOGS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS activity_logs;
CREATE TABLE activity_logs (
    log_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_type    ENUM('admin','user','system') NOT NULL,
    actor_id        INT UNSIGNED NULL,
    action            VARCHAR(80) NOT NULL,
    description         TEXT NULL,
    ip_address            VARCHAR(45) NULL,
    created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_logs_actor (actor_type, actor_id),
    INDEX idx_logs_date (created_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. SYSTEM SETTINGS (key/value config, editable from Admin Panel)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS system_settings;
CREATE TABLE system_settings (
    setting_key    VARCHAR(60) PRIMARY KEY,
    setting_value   VARCHAR(255) NOT NULL,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- Seed data
-- =====================================================================

INSERT INTO system_settings (setting_key, setting_value) VALUES
    ('grace_period_minutes', '30'),
    ('default_hourly_rate', '100.00'),
    ('currency_symbol', 'Rs.'),
    ('site_name', 'Smart Vehicle Parking Management System'),
    ('timezone', 'Asia/Colombo');

-- Default admin: username "admin", password "Admin@123"
-- (hash generated with PHP password_hash('Admin@123', PASSWORD_BCRYPT))
INSERT INTO admins (username, email, password_hash, full_name, role) VALUES
    ('admin', 'admin@smartparking.local', '$2y$10$bgU0sdb56F0rkFOWneiMjOP8r0dnB.7P9Gt/sYKnmZa6a3/HMPR6G', 'System Administrator', 'super_admin');

-- 20 sample parking slots across two zones
INSERT INTO parking_slots (slot_code, zone, slot_type, vehicle_types, capacity, hourly_rate) VALUES
-- Zone A: 10 car slots, 5 bike slots
('A-01','Zone A','car','car',1,100.00),('A-02','Zone A','car','car',1,100.00),('A-03','Zone A','car','car',1,100.00),
('A-04','Zone A','car','car',1,100.00),('A-05','Zone A','car','car',1,100.00),('A-06','Zone A','car','car',1,100.00),
('A-07','Zone A','car','car',1,100.00),('A-08','Zone A','car','car',1,100.00),('A-09','Zone A','car','car',1,100.00),
('A-10','Zone A','car','car',1,100.00),
('A-B1','Zone A','bike','bike',10,50.00),('A-B2','Zone A','bike','bike',10,50.00),
('A-B3','Zone A','bike','bike',10,50.00),('A-B4','Zone A','bike','bike',10,50.00),
('A-B5','Zone A','bike','bike',10,50.00),
-- Zone B: 8 car slots, 4 van slots, 3 bike slots
('B-01','Zone B','car','car',1,100.00),('B-02','Zone B','car','car',1,100.00),('B-03','Zone B','car','car',1,100.00),
('B-04','Zone B','car','car',1,100.00),('B-05','Zone B','car','car',1,100.00),('B-06','Zone B','car','car',1,100.00),
('B-07','Zone B','car','car',1,100.00),('B-08','Zone B','car','car',1,100.00),
('B-V1','Zone B','van','van',1,130.00),('B-V2','Zone B','van','van',1,130.00),
('B-V3','Zone B','van','van',1,130.00),('B-V4','Zone B','van','van',1,130.00),
('B-B1','Zone B','bike','bike',10,50.00),('B-B2','Zone B','bike','bike',10,50.00),
('B-B3','Zone B','bike','bike',10,50.00),
-- Zone C: mixed
('C-01','Zone C','car','car',1,110.00),('C-02','Zone C','car','car',1,110.00),('C-03','Zone C','car','car',1,110.00),
('C-04','Zone C','car','car',1,110.00),('C-05','Zone C','car','car',1,110.00),
('C-V1','Zone C','van','van',1,140.00),('C-V2','Zone C','van','van',1,140.00),
('C-B1','Zone C','bike','bike',10,55.00),('C-B2','Zone C','bike','bike',10,55.00);
