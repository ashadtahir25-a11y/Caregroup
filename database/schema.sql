-- CARE Group Medical Services Database Schema
-- Run this in your MySQL server (e.g., PHPMyAdmin)

CREATE DATABASE IF NOT EXISTS care_db;
USE care_db;

-- 1. Users Table (Handles Logins)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'doctor', 'patient') NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Cities Table
CREATE TABLE IF NOT EXISTS cities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    country VARCHAR(100) DEFAULT 'Pakistan'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Doctors Table
CREATE TABLE IF NOT EXISTS doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    specialty VARCHAR(100) NOT NULL,
    city_id INT NOT NULL,
    address TEXT NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    bio TEXT,
    experience_years INT DEFAULT 0,
    consultation_fee DECIMAL(10, 2) DEFAULT 0.00,
    available_days VARCHAR(255) DEFAULT 'Monday,Tuesday,Wednesday,Thursday,Friday', -- Comma-separated days
    available_slots VARCHAR(255) DEFAULT '09:00 AM,11:00 AM,02:00 PM,04:00 PM', -- Comma-separated slots
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE RESTRICT  -- a city with doctors cannot be deleted
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Patients Table
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    registered_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Appointments Table
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    patient_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    time_slot VARCHAR(50) NOT NULL,
    status ENUM('Pending', 'Confirmed', 'Completed', 'Cancelled') DEFAULT 'Pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Diseases Table (Common Diseases, Preventions & Cures)
CREATE TABLE IF NOT EXISTS diseases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    symptoms TEXT NOT NULL, -- Comma or semi-colon separated
    preventions TEXT NOT NULL, -- Comma or newline separated
    cures TEXT NOT NULL, -- Comma or newline separated
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Medical News & Inventions Table
CREATE TABLE IF NOT EXISTS medical_news (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    summary TEXT NOT NULL,
    content TEXT NOT NULL,
    category ENUM('Invention', 'News', 'Research') NOT NULL,
    published_date DATE NOT NULL,
    author VARCHAR(100) NOT NULL DEFAULT 'CARE Group Editor'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Login attempts (brute-force protection for both login pages)
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scope ENUM('public', 'admin') NOT NULL,
    ip VARCHAR(45) NOT NULL,
    username VARCHAR(50) NOT NULL DEFAULT '',
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_scope_ip_time (scope, ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- SEED DEFAULT MASTER DATA
-- A. Default administrator. Username: admin  Password: admin123
--    Change this password after your first sign-in.
INSERT INTO users (username, password, role, email)
VALUES ('admin', '$2y$10$c.1DEGr8d92lSd6AAG23WeKxWvf1UvhYeu1CWn8ppf8hNQdaOPF/q', 'admin', 'admin@caregroup.com')
ON DUPLICATE KEY UPDATE id = id;

-- B. Default Cities
INSERT INTO cities (name, country) VALUES 
('Karachi', 'Pakistan'),
('Lahore', 'Pakistan'),
('Islamabad', 'Pakistan'),
('Rawalpindi', 'Pakistan'),
('Faisalabad', 'Pakistan')
ON DUPLICATE KEY UPDATE id=id;

-- C. Sample Diseases Information
INSERT INTO diseases (name, description, symptoms, preventions, cures) VALUES
('Influenza (Flu)', 'Influenza is a common viral infection that attacks your lungs, nose and throat.', 'Fever, cough, sore throat, runny nose, muscle aches, fatigue', 'Get a yearly flu vaccine, wash hands frequently, avoid close contact with sick people', 'Rest, fluids, over-the-counter pain relievers, antiviral medications (if prescribed)'),

('Hypertension (High Blood Pressure)', 'A condition in which the force of the blood against the artery walls is too high.', 'Often quiet or asymptomatic; headaches, shortness of breath, nosebleeds in extreme cases', 'Eat a healthy low-salt diet, exercise regularly, maintain a healthy weight, limit alcohol', 'Antihypertensive medications, lifestyle adjustments, blood pressure monitoring'),

('Diabetes Mellitus (Type 2)', 'A chronic condition that affects the way the body processes blood sugar (glucose).', 'Increased thirst, frequent urination, hunger, fatigue, blurry vision', 'Maintain a healthy weight, regular physical activity, balanced fiber-rich low-sugar diet', 'Metformin or insulin therapy, blood sugar monitoring, regular doctor checkups'),

('Dengue Fever', 'A mosquito-borne viral disease causing high fever, rash, and muscle pain.', 'Severe headache, pain behind the eyes, high fever, skin rash, joint and muscle pain', 'Use mosquito repellents, wear long sleeves, remove standing water around the house, sleep under mosquito nets', 'Hydration, supportive care, paracetamol for pain (avoid ibuprofen/aspirin to reduce bleeding risk)'),

('Covid-19', 'An infectious disease caused by the SARS-CoV-2 virus, leading to respiratory symptoms.', 'Fever, dry cough, tiredness, difficulty breathing, loss of taste or smell', 'Wear masks, stay vaccinated, maintain physical distance, wash hands often', 'Isolation, symptom management, oxygen support for severe cases, antivirals (Paxlovid)')
ON DUPLICATE KEY UPDATE id=id;

-- D. Sample Medical News & Inventions
INSERT INTO medical_news (title, summary, content, category, published_date, author) VALUES
('AI Tool Correctly Identifies Cardiac Arrest in Real Time', 'A newly developed AI diagnostic model analyzes audio data from emergency services to match cardiac patterns.', 'Scientists have trained a neural network using over 100,000 emergency system calls. The program detects subtle breathing patterns and distress signatures associated with cardiac arrest, achieving a massive 95% accuracy rate, significantly faster than human emergency operators.', 'Invention', '2026-05-18', 'Dr. Alistair Vance'),

('Breakthrough in Malaria Vaccine Deployment across Africa', 'A new cost-effective, highly scalable vaccine receives emergency approvals for rural regions.', 'The R21/Matrix-M malaria vaccine, developed by the University of Oxford, shows up to 75% efficacy. Large-scale rollout has commenced in Ghana, Nigeria, and Kenya, promising to save hundreds of thousands of toddlers from the deadly parasitic disease annually.', 'News', '2026-05-24', 'Sarah Jenkins'),

('Gene-Editing Cure for Sickle Cell Disease Enters Human Trials', 'CRISPR-Cas9 based therapeutic designs have succeeded in early stages of genomic correction trials.', 'The first patient groups undergoing standard gene-editing therapies have completed a 12-month window. Results show complete recovery or substantial reduction in vaso-occlusive pain crises, effectively offering a functional cure for this age-old genetic blood disorder.', 'Research', '2026-05-30', 'Prof. Tariq Mahmood')
ON DUPLICATE KEY UPDATE id=id;
