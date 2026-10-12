-- HireSwipe: auth + onboarding schema (MySQL 8 / MariaDB, InnoDB, utf8mb4)
CREATE DATABASE IF NOT EXISTS hireswipe CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hireswipe;

-- ---------- Lookup tables ----------
CREATE TABLE industries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE employment_types (           -- Full-time, Part-time, Contract, Internship...
  id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ---------- Core auth ----------
CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  mobile VARCHAR(20) NOT NULL UNIQUE,            -- store with country code, e.g. +919876543210
  password_hash VARCHAR(255) NOT NULL,           -- password_hash() in PHP; never store plain/confirm password
  role ENUM('employee','employer') NULL,         -- NULL until user picks on onboarding screen
  is_email_verified TINYINT(1) NOT NULL DEFAULT 0,
  is_mobile_verified TINYINT(1) NOT NULL DEFAULT 0,
  onboarding_completed TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','blocked','deleted') NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- OTP for mobile login / signup verification / forgot password
CREATE TABLE otp_verifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,                  -- NULL if OTP sent before account exists
  target VARCHAR(190) NOT NULL,                  -- mobile number or email the OTP was sent to
  channel ENUM('sms','email') NOT NULL DEFAULT 'sms',
  purpose ENUM('signup','login','forgot_password') NOT NULL,
  otp_hash VARCHAR(255) NOT NULL,                -- store hashed OTP
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  is_used TINYINT(1) NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_target_purpose (target, purpose, is_used),
  CONSTRAINT fk_otp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Forgot password (email link) tokens
CREATE TABLE password_resets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,                  -- SHA-256 of the emailed token
  is_used TINYINT(1) NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_token (token_hash),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Login sessions / remember-me / logout everywhere
CREATE TABLE user_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  session_token_hash CHAR(64) NOT NULL UNIQUE,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_session_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Brute-force protection / audit
CREATE TABLE login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identifier VARCHAR(190) NOT NULL,              -- email or mobile tried
  ip_address VARCHAR(45) NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_identifier_time (identifier, created_at)
) ENGINE=InnoDB;

-- ---------- Employee (job seeker) onboarding ----------
CREATE TABLE employee_profiles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  full_name VARCHAR(120) NOT NULL,
  age TINYINT UNSIGNED NULL,                     -- consider date_of_birth instead so age never goes stale
  location VARCHAR(150) NOT NULL,
  nationality VARCHAR(80) NULL,
  qualification VARCHAR(150) NULL,
  experience_years DECIMAL(4,1) NOT NULL DEFAULT 0,
  expected_salary DECIMAL(12,2) NULL,
  salary_currency CHAR(3) NOT NULL DEFAULT 'INR',
  employment_type_id TINYINT UNSIGNED NULL,
  profile_picture_path VARCHAR(255) NULL,        -- store file path/URL, not the image blob
  cv_path VARCHAR(255) NULL,                     -- uploaded CV file path
  cv_text MEDIUMTEXT NULL,                       -- optional parsed CV data
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_emp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_emp_type FOREIGN KEY (employment_type_id) REFERENCES employment_types(id)
) ENGINE=InnoDB;

-- Many industries per employee
CREATE TABLE employee_industries (
  employee_id BIGINT UNSIGNED NOT NULL,
  industry_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (employee_id, industry_id),
  CONSTRAINT fk_ei_emp FOREIGN KEY (employee_id) REFERENCES employee_profiles(id) ON DELETE CASCADE,
  CONSTRAINT fk_ei_ind FOREIGN KEY (industry_id) REFERENCES industries(id)
) ENGINE=InnoDB;

-- ---------- Employer onboarding ----------
CREATE TABLE employer_profiles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  company_name VARCHAR(150) NOT NULL,
  location VARCHAR(150) NOT NULL,
  industry_id INT UNSIGNED NULL,
  logo_path VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_empr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_empr_ind FOREIGN KEY (industry_id) REFERENCES industries(id)
) ENGINE=InnoDB;

-- Position/salary/job detail belong to a job post, so one employer can post many jobs
CREATE TABLE job_posts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employer_id BIGINT UNSIGNED NOT NULL,
  position VARCHAR(150) NOT NULL,
  employment_type_id TINYINT UNSIGNED NULL,
  salary_min DECIMAL(12,2) NULL,
  salary_max DECIMAL(12,2) NULL,
  salary_currency CHAR(3) NOT NULL DEFAULT 'INR',
  job_detail TEXT NOT NULL,
  status ENUM('draft','open','closed') NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_employer (employer_id),
  CONSTRAINT fk_job_employer FOREIGN KEY (employer_id) REFERENCES employer_profiles(id) ON DELETE CASCADE,
  CONSTRAINT fk_job_type FOREIGN KEY (employment_type_id) REFERENCES employment_types(id)
) ENGINE=InnoDB;

-- ---------- Seed data ----------
INSERT INTO employment_types (name) VALUES ('Full-time'),('Part-time'),('Contract'),('Internship'),('Freelance');
INSERT INTO industries (name) VALUES ('IT & Software'),('Finance'),('Healthcare'),('Education'),('Manufacturing'),('Retail'),('Marketing'),('Hospitality');
