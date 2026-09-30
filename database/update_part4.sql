-- ==========================================================
-- CARE Group - Part 4 update (run once in phpMyAdmin on care_db)
-- New features: prescriptions, reviews, and specialist
-- suggestions for the symptom checker. Existing data is kept.
-- ==========================================================
USE care_db;

-- 1. Prescription written by the doctor when a visit is completed
CREATE TABLE IF NOT EXISTS prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL UNIQUE,
    diagnosis TEXT NOT NULL,
    medicines TEXT NOT NULL,
    advice TEXT,
    follow_up DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. One review per completed appointment
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL UNIQUE,
    doctor_id INT NOT NULL,
    patient_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    INDEX idx_doctor (doctor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Which specialist to see for each condition (used by the symptom checker)
ALTER TABLE diseases ADD COLUMN IF NOT EXISTS specialty VARCHAR(100) NULL AFTER name;

UPDATE diseases SET specialty = 'General Physician' WHERE specialty IS NULL AND name LIKE 'Influenza%';
UPDATE diseases SET specialty = 'Cardiologist'      WHERE specialty IS NULL AND name LIKE 'Hypertension%';
UPDATE diseases SET specialty = 'Endocrinologist'   WHERE specialty IS NULL AND name LIKE 'Diabetes%';
UPDATE diseases SET specialty = 'General Physician' WHERE specialty IS NULL AND name LIKE 'Dengue%';
UPDATE diseases SET specialty = 'Pulmonologist'     WHERE specialty IS NULL AND name LIKE 'Covid%';
