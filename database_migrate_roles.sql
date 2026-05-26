-- Run this ONLY if you already have hospital_system from an older schema.sql
-- Fresh installs should use database.sql instead.

USE hospital_system;

CREATE TABLE IF NOT EXISTS admins (
  admin_id      INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(150) NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uk_admins_username UNIQUE (username)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_logs (
  log_id                INT AUTO_INCREMENT PRIMARY KEY,
  action_type           ENUM('DOCTOR_ADDED', 'DOCTOR_DELETED') NOT NULL,
  entity_type           VARCHAR(50)  NOT NULL DEFAULT 'doctor',
  entity_id             INT          NOT NULL,
  details               VARCHAR(500) NOT NULL,
  performed_by_admin_id INT          NULL,
  log_created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_system_logs_admin
    FOREIGN KEY (performed_by_admin_id) REFERENCES admins (admin_id)
      ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

ALTER TABLE doctors
  ADD COLUMN IF NOT EXISTS username VARCHAR(50) NULL AFTER phone,
  ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL AFTER username,
  ADD COLUMN IF NOT EXISTS gender ENUM('M', 'F', 'Other') NULL AFTER password_hash,
  ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255) NULL AFTER gender,
  ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER profile_picture;

ALTER TABLE doctors ADD UNIQUE INDEX uk_doctors_username (username);

INSERT INTO admins (username, password_hash, full_name)
SELECT 'admin', '$2y$10$U3Dr3zOsU2pVlO8jtQr0i.GdkNVGfSs0iJYG8uJDf7GfG2Iq7kL1S', 'System Administrator'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM admins WHERE username = 'admin');

-- Triggers and sp_admin_dashboard_stats: re-run relevant sections from database.sql in phpMyAdmin.
