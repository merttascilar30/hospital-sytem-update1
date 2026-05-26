-- Hospital Appointment System — MySQL schema
--
-- Third Normal Form (3NF) design:
--   • Every table has a primary key; non-key columns depend only on that key.
--   • No repeating groups: patients, doctors, departments, and appointments are separate entities.
--   • Transitive dependencies removed: doctor belongs to department via department_id FK only;
--     appointment links patient and doctor via FKs (no duplicated names on appointments).

CREATE DATABASE IF NOT EXISTS hospital_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE hospital_system;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS appointment_logs;
DROP TABLE IF EXISTS system_logs;
DROP TABLE IF EXISTS doctors;
DROP TABLE IF EXISTS patients;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS departments;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE departments (
  department_id INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  description   VARCHAR(255),
  CONSTRAINT uk_departments_name UNIQUE (name)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE patients (
  patient_id    INT AUTO_INCREMENT PRIMARY KEY,
  first_name    VARCHAR(100) NOT NULL,
  last_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL,
  phone         VARCHAR(20),
  birth_date    DATE,
  gender        ENUM('M', 'F', 'Other'),
  password_hash VARCHAR(255) NOT NULL,
  notes         TEXT,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uk_patients_email UNIQUE (email)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE admins (
  admin_id      INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(150) NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uk_admins_username UNIQUE (username)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE doctors (
  doctor_id       INT AUTO_INCREMENT PRIMARY KEY,
  department_id   INT          NOT NULL,
  first_name      VARCHAR(100) NOT NULL,
  last_name       VARCHAR(100) NOT NULL,
  email           VARCHAR(150) NOT NULL,
  phone           VARCHAR(20),
  username        VARCHAR(50),
  password_hash   VARCHAR(255),
  gender          ENUM('M', 'F', 'Other'),
  profile_picture VARCHAR(255),
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uk_doctors_email UNIQUE (email),
  CONSTRAINT uk_doctors_username UNIQUE (username),
  CONSTRAINT fk_doctors_department
    FOREIGN KEY (department_id) REFERENCES departments (department_id)
      ON UPDATE CASCADE
      ON DELETE RESTRICT
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

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

CREATE TABLE appointment_logs (
  log_id            INT AUTO_INCREMENT PRIMARY KEY,
  appointment_id    INT        NOT NULL,
  patient_id        INT        NOT NULL,
  doctor_id         INT        NOT NULL,
  action_type       ENUM('CREATED', 'UPDATED', 'DELETED') NOT NULL,
  previous_datetime DATETIME   NULL,
  previous_notes    TEXT       NULL,
  log_created_at    DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- Audit trail for admin doctor management (populated by triggers on doctors).
CREATE TABLE system_logs (
  log_id                INT AUTO_INCREMENT PRIMARY KEY,
  action_type           ENUM('DOCTOR_ADDED', 'DOCTOR_DELETED') NOT NULL,
  entity_type           VARCHAR(50)  NOT NULL DEFAULT 'doctor',
  entity_id             INT          NOT NULL,
  details               VARCHAR(500) NOT NULL,
  performed_by_admin_id INT          NULL,
  log_created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_system_logs_admin
    FOREIGN KEY (performed_by_admin_id) REFERENCES admins (admin_id)
      ON UPDATE CASCADE
      ON DELETE SET NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- Default admin (password: Admin123!) — change after first login in production.
INSERT INTO admins (username, password_hash, full_name) VALUES (
  'admin',
  '$2y$10$U3Dr3zOsU2pVlO8jtQr0i.GdkNVGfSs0iJYG8uJDf7GfG2Iq7kL1S',
  'System Administrator'
);

-- ---------------------------------------------------------------------------
-- Triggers — appointment workflow
-- ---------------------------------------------------------------------------

DROP TRIGGER IF EXISTS trg_appointments_set_updated_at;
DELIMITER $$
CREATE TRIGGER trg_appointments_set_updated_at
BEFORE UPDATE ON appointments
FOR EACH ROW
BEGIN
  SET NEW.updated_at = CURRENT_TIMESTAMP;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_appointments_log_insert;
DELIMITER $$
CREATE TRIGGER trg_appointments_log_insert
AFTER INSERT ON appointments
FOR EACH ROW
BEGIN
  INSERT INTO appointment_logs (appointment_id, patient_id, doctor_id, action_type, previous_datetime, previous_notes)
  VALUES (NEW.appointment_id, NEW.patient_id, NEW.doctor_id, 'CREATED', NULL, NULL);
END$$
DELIMITER ;

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

-- ---------------------------------------------------------------------------
-- Triggers — admin doctor management → system_logs
-- Set @current_admin_id from PHP before INSERT/DELETE on doctors.
-- ---------------------------------------------------------------------------

DROP TRIGGER IF EXISTS trg_doctors_log_insert;
DELIMITER $$
CREATE TRIGGER trg_doctors_log_insert
AFTER INSERT ON doctors
FOR EACH ROW
BEGIN
  INSERT INTO system_logs (action_type, entity_type, entity_id, details, performed_by_admin_id)
  VALUES (
    'DOCTOR_ADDED',
    'doctor',
    NEW.doctor_id,
    CONCAT(NEW.first_name, ' ', NEW.last_name, ' [', IFNULL(NEW.username, 'no-username'), ']'),
    @current_admin_id
  );
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_doctors_log_delete;
DELIMITER $$
CREATE TRIGGER trg_doctors_log_delete
BEFORE DELETE ON doctors
FOR EACH ROW
BEGIN
  INSERT INTO system_logs (action_type, entity_type, entity_id, details, performed_by_admin_id)
  VALUES (
    'DOCTOR_DELETED',
    'doctor',
    OLD.doctor_id,
    CONCAT(OLD.first_name, ' ', OLD.last_name, ' [', IFNULL(OLD.username, 'no-username'), ']'),
    @current_admin_id
  );
END$$
DELIMITER ;

-- ---------------------------------------------------------------------------
-- Stored procedures
-- ---------------------------------------------------------------------------

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

DROP PROCEDURE IF EXISTS sp_cancel_appointment_safe;
DELIMITER $$
CREATE PROCEDURE sp_cancel_appointment_safe (
  IN p_appointment_id INT,
  IN p_patient_id     INT,
  OUT p_rows_deleted  INT
)
BEGIN
  DELETE FROM appointments
  WHERE appointment_id = p_appointment_id
    AND patient_id = p_patient_id;
  SET p_rows_deleted = ROW_COUNT();
END$$
DELIMITER ;

-- Admin dashboard: total doctors + appointments per polyclinic (department).
DROP PROCEDURE IF EXISTS sp_admin_dashboard_stats;
DELIMITER $$
CREATE PROCEDURE sp_admin_dashboard_stats ()
BEGIN
  SELECT COUNT(*) AS total_doctors
  FROM doctors;

  SELECT
    dep.department_id,
    dep.name AS polyclinic_name,
    COUNT(DISTINCT d.doctor_id) AS doctor_count,
    COUNT(a.appointment_id) AS appointment_count
  FROM departments dep
  LEFT JOIN doctors d ON d.department_id = dep.department_id
  LEFT JOIN appointments a ON a.doctor_id = d.doctor_id
  GROUP BY dep.department_id, dep.name
  ORDER BY dep.name;
END$$
DELIMITER ;
