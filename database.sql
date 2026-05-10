-- Hospital Appointment System - Database Schema (Phase 1: Database Foundation)

-- Ensure database exists
CREATE DATABASE IF NOT EXISTS hospital_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE hospital_system;

-- Drop tables in reverse dependency order (for re-runs during development)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS appointment_logs;
DROP TABLE IF EXISTS doctors;
DROP TABLE IF EXISTS patients;
DROP TABLE IF EXISTS departments;
SET FOREIGN_KEY_CHECKS = 1;

-- Departments table
CREATE TABLE departments (
  department_id INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  description   VARCHAR(255)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- Doctors table
CREATE TABLE doctors (
  doctor_id     INT AUTO_INCREMENT PRIMARY KEY,
  department_id INT          NOT NULL,
  first_name    VARCHAR(100) NOT NULL,
  last_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  phone         VARCHAR(20),
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_doctors_department
    FOREIGN KEY (department_id) REFERENCES departments (department_id)
      ON UPDATE CASCADE
      ON DELETE RESTRICT
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- Patients table
CREATE TABLE patients (
  patient_id  INT AUTO_INCREMENT PRIMARY KEY,
  first_name  VARCHAR(100) NOT NULL,
  last_name   VARCHAR(100) NOT NULL,
  email       VARCHAR(150) NOT NULL UNIQUE,
  phone       VARCHAR(20),
  birth_date  DATE,
  gender      ENUM('M', 'F', 'Other'),
  password_hash VARCHAR(255) NOT NULL,
  notes       TEXT,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- Appointments table
CREATE TABLE appointments (
  appointment_id       INT AUTO_INCREMENT PRIMARY KEY,
  patient_id           INT          NOT NULL,
  doctor_id            INT          NOT NULL,
  appointment_datetime DATETIME     NOT NULL,
  status               ENUM('scheduled', 'completed', 'cancelled')
                                  NOT NULL DEFAULT 'scheduled',
  notes                TEXT,
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME     NULL,
  CONSTRAINT fk_appointments_patient
    FOREIGN KEY (patient_id) REFERENCES patients (patient_id)
      ON UPDATE CASCADE
      ON DELETE RESTRICT,
  CONSTRAINT fk_appointments_doctor
    FOREIGN KEY (doctor_id) REFERENCES doctors (doctor_id)
      ON UPDATE CASCADE
      ON DELETE RESTRICT,
  CONSTRAINT uk_doctor_appointment_datetime
    UNIQUE (doctor_id, appointment_datetime)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- Appointment logs table (for audit trail)
CREATE TABLE appointment_logs (
  log_id        INT AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT        NOT NULL,
  patient_id     INT        NOT NULL,
  doctor_id      INT        NOT NULL,
  action_type    ENUM('UPDATED', 'DELETED') NOT NULL,
  previous_datetime DATETIME NULL,
  previous_notes    TEXT     NULL,
  log_created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- Trigger: automatically update updated_at on appointment updates
DROP TRIGGER IF EXISTS trg_appointments_set_updated_at;
DELIMITER $$
CREATE TRIGGER trg_appointments_set_updated_at
BEFORE UPDATE ON appointments
FOR EACH ROW
BEGIN
  SET NEW.updated_at = CURRENT_TIMESTAMP;
END$$
DELIMITER ;

-- Trigger: log updates to appointments
DROP TRIGGER IF EXISTS trg_appointments_log_update;
DELIMITER $$
CREATE TRIGGER trg_appointments_log_update
BEFORE UPDATE ON appointments
FOR EACH ROW
BEGIN
  INSERT INTO appointment_logs (appointment_id, patient_id, doctor_id, action_type, previous_datetime, previous_notes)
  VALUES (OLD.appointment_id, OLD.patient_id, OLD.doctor_id, 'UPDATED', OLD.appointment_datetime, OLD.notes);
END$$
DELIMITER ;

-- Trigger: log deletions of appointments
DROP TRIGGER IF EXISTS trg_appointments_log_delete;
DELIMITER $$
CREATE TRIGGER trg_appointments_log_delete
BEFORE DELETE ON appointments
FOR EACH ROW
BEGIN
  INSERT INTO appointment_logs (appointment_id, patient_id, doctor_id, action_type, previous_datetime, previous_notes)
  VALUES (OLD.appointment_id, OLD.patient_id, OLD.doctor_id, 'DELETED', OLD.appointment_datetime, OLD.notes);
END$$
DELIMITER ;

-- Stored Procedure: get appointments for a given doctor within a date range
DROP PROCEDURE IF EXISTS sp_get_doctor_appointments;
DELIMITER $$
CREATE PROCEDURE sp_get_doctor_appointments (
  IN p_doctor_id INT,
  IN p_start     DATETIME,
  IN p_end       DATETIME
)
BEGIN
  SELECT
    a.appointment_id,
    a.appointment_datetime,
    a.status,
    a.notes,
    a.created_at,
    a.updated_at,
    p.patient_id,
    p.first_name  AS patient_first_name,
    p.last_name   AS patient_last_name
  FROM appointments a
  INNER JOIN patients p ON p.patient_id = a.patient_id
  WHERE a.doctor_id = p_doctor_id
    AND a.appointment_datetime BETWEEN p_start AND p_end
  ORDER BY a.appointment_datetime;
END$$
DELIMITER ;

-- Stored Procedure: get total appointments for a patient
DROP PROCEDURE IF EXISTS sp_get_patient_appointment_count;
DELIMITER $$
CREATE PROCEDURE sp_get_patient_appointment_count (
  IN p_patient_id INT
)
BEGIN
  SELECT COUNT(*) AS total_appointments
  FROM appointments
  WHERE patient_id = p_patient_id;
END$$
DELIMITER ;

