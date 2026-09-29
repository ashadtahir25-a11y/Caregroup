-- ==========================================================
-- CARE Group - Part 1 update for an EXISTING database
-- Run this once in phpMyAdmin (SQL tab) on care_group_db.
-- Your doctors, patients and appointments are kept.
-- ==========================================================
USE care_group_db;

-- 1. Table used to lock the login pages after repeated wrong passwords
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scope ENUM('public', 'admin') NOT NULL,
    ip VARCHAR(45) NOT NULL,
    username VARCHAR(50) NOT NULL DEFAULT '',
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_scope_ip_time (scope, ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Give the admin a real password hash for "admin123".
--    The old seeded hash never matched; login only worked through a hard-coded
--    shortcut, which has now been removed.
UPDATE users
   SET password = '$2y$10$c.1DEGr8d92lSd6AAG23WeKxWvf1UvhYeu1CWn8ppf8hNQdaOPF/q'
 WHERE username = 'admin' AND role = 'admin';

-- 3. Deleting a city must NOT silently delete its doctors and appointments.
--    Replace ON DELETE CASCADE with ON DELETE RESTRICT.
--    The constraint name is looked up automatically, so this works on any copy.
SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'doctors'
               AND COLUMN_NAME = 'city_id' AND REFERENCED_TABLE_NAME = 'cities' LIMIT 1);
SET @sql := IF(@fk IS NULL, 'SELECT 1', CONCAT('ALTER TABLE doctors DROP FOREIGN KEY `', @fk, '`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE doctors
  ADD CONSTRAINT fk_doctors_city FOREIGN KEY (city_id) REFERENCES cities (id) ON DELETE RESTRICT;
