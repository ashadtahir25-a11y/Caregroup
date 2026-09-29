-- ==========================================================
-- CARE Group - Part 3 update (run once in phpMyAdmin on care_db)
-- Stops two patients from booking the same doctor, date and time.
-- Your existing data is kept.
-- ==========================================================
USE care_db;

-- active_slot is 1 for live bookings and NULL for cancelled ones.
-- A UNIQUE index ignores NULLs, so a cancelled slot can be booked again.
ALTER TABLE appointments
  ADD COLUMN IF NOT EXISTS active_slot TINYINT
      AS (IF(status = 'Cancelled', NULL, 1)) STORED;

ALTER TABLE appointments
  ADD UNIQUE INDEX IF NOT EXISTS uniq_doctor_slot (doctor_id, appointment_date, time_slot, active_slot);

-- Faster look-ups for dashboards
ALTER TABLE appointments ADD INDEX IF NOT EXISTS idx_patient (patient_id);