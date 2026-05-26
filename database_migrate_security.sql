-- Run on existing hospital_system database if patients table lacks security columns.
USE hospital_system;

ALTER TABLE patients
  ADD COLUMN security_question VARCHAR(255) NULL AFTER password_hash,
  ADD COLUMN security_answer VARCHAR(255) NULL AFTER security_question;
