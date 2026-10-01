-- ==========================================================
-- CARE Group - Part 5 update (run once in phpMyAdmin on care_db)
-- Notifications, contact inbox, reschedule limit, review moderation.
-- Safe to run more than once. Existing data is kept.
-- ==========================================================
USE care_db;

-- 1. In-app notifications (the bell on every dashboard)
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(30) NOT NULL,
    title VARCHAR(150) NOT NULL,
    body VARCHAR(255) NOT NULL DEFAULT '',
    link VARCHAR(255) NOT NULL DEFAULT '',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Messages sent from the public Contact page
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NULL,
    subject VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('New', 'Read', 'Archived') NOT NULL DEFAULT 'New',
    ip VARCHAR(45) NOT NULL DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status, created_at),
    INDEX idx_ip_time (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. How many times a patient moved an appointment (limit is 2)
ALTER TABLE appointments ADD COLUMN IF NOT EXISTS reschedule_count TINYINT NOT NULL DEFAULT 0;

-- 4. Admin can hide a review (with a reason) instead of deleting it
ALTER TABLE reviews ADD COLUMN IF NOT EXISTS is_hidden TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE reviews ADD COLUMN IF NOT EXISTS hidden_reason VARCHAR(255) NULL;
