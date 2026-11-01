-- Victory Phase 2 management schema migration.
-- Target: MariaDB 10.4.32, after verifying the documented preconditions.
-- This is a staged, one-time migration. Do not rerun without inspecting the
-- live schema and recording which DDL stages have already completed.

-- Stage A: account status and one-time setup-token storage.
ALTER TABLE users
  ADD COLUMN account_status ENUM('active','inactive','pending_setup') NOT NULL DEFAULT 'active' AFTER role,
  ADD COLUMN setup_token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  ADD COLUMN setup_token_expires_at DATETIME DEFAULT NULL,
  ADD UNIQUE KEY uq_users_setup_token_hash (setup_token_hash),
  ADD KEY idx_users_role_account_status (role, account_status);

-- Stage B: job-seeker classification and subtype tables.
ALTER TABLE job_seekers
  ADD COLUMN profile_type ENUM('student','professional','unclassified') NOT NULL DEFAULT 'unclassified' AFTER user_id,
  ADD UNIQUE KEY uq_job_seekers_user_id (user_id);

CREATE TABLE student_profiles (
  js_id INT(11) NOT NULL,
  college_university VARCHAR(180) DEFAULT NULL,
  course VARCHAR(150) DEFAULT NULL,
  branch VARCHAR(150) DEFAULT NULL,
  current_year_semester VARCHAR(50) DEFAULT NULL,
  graduation_year SMALLINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (js_id),
  CONSTRAINT fk_student_profiles_job_seeker
    FOREIGN KEY (js_id) REFERENCES job_seekers (js_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE professional_profiles (
  js_id INT(11) NOT NULL,
  highest_qualification VARCHAR(150) DEFAULT NULL,
  current_previous_company VARCHAR(180) DEFAULT NULL,
  experience_years DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  expected_salary DECIMAL(12,2) DEFAULT NULL,
  PRIMARY KEY (js_id),
  CONSTRAINT fk_professional_profiles_job_seeker
    FOREIGN KEY (js_id) REFERENCES job_seekers (js_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE companies (
  company_id INT(11) NOT NULL AUTO_INCREMENT,
  company_name VARCHAR(150) NOT NULL,
  industry VARCHAR(100) DEFAULT NULL,
  website VARCHAR(255) DEFAULT NULL,
  official_email VARCHAR(100) DEFAULT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  location VARCHAR(150) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  logo VARCHAR(255) DEFAULT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  migration_employer_id INT(11) DEFAULT NULL,
  PRIMARY KEY (company_id),
  UNIQUE KEY uq_companies_migration_employer (migration_employer_id),
  KEY idx_companies_status_name (status, company_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE employers
  ADD COLUMN recruiter_name VARCHAR(100) DEFAULT NULL,
  ADD COLUMN phone VARCHAR(20) DEFAULT NULL,
  ADD COLUMN designation VARCHAR(100) DEFAULT NULL,
  ADD COLUMN company_id INT(11) DEFAULT NULL,
  ADD UNIQUE KEY uq_employers_user_id (user_id),
  ADD KEY idx_employers_company_id (company_id);

CREATE TABLE admin_audit_log (
  audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_user_id INT(11) DEFAULT NULL,
  action VARCHAR(50) NOT NULL,
  entity_type VARCHAR(50) NOT NULL,
  entity_id INT(11) DEFAULT NULL,
  summary VARCHAR(255) DEFAULT NULL,
  details_json LONGTEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (audit_id),
  KEY idx_admin_audit_actor_time (actor_user_id, created_at),
  KEY idx_admin_audit_entity (entity_type, entity_id),
  CONSTRAINT fk_admin_audit_actor
    FOREIGN KEY (actor_user_id) REFERENCES users (user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Stage C: add only missing profile rows; do not infer personal information.
START TRANSACTION;

INSERT INTO job_seekers (user_id)
SELECT u.user_id
FROM users AS u
LEFT JOIN job_seekers AS js ON js.user_id = u.user_id
WHERE u.role = 'jobseeker' AND js.js_id IS NULL;

INSERT INTO employers (user_id)
SELECT u.user_id
FROM users AS u
LEFT JOIN employers AS e ON e.user_id = u.user_id
WHERE u.role = 'employer' AND e.emp_id IS NULL;

-- Create a separate company for each legacy employer profile; never merge
-- organizations based only on equal names.
INSERT INTO companies
  (company_name, industry, location, description, logo, migration_employer_id)
SELECT TRIM(e.company_name), NULLIF(TRIM(e.industry), ''),
       NULLIF(TRIM(e.location), ''), NULLIF(TRIM(e.about), ''),
       NULLIF(TRIM(e.logo), ''), e.emp_id
FROM employers AS e
WHERE e.company_name IS NOT NULL AND TRIM(e.company_name) <> '';

UPDATE employers AS e
JOIN companies AS c ON c.migration_employer_id = e.emp_id
SET e.company_id = c.company_id;

COMMIT;

-- Stage D: remove the temporary mapping and add the organization relation.
ALTER TABLE companies
  DROP INDEX uq_companies_migration_employer,
  DROP COLUMN migration_employer_id;

ALTER TABLE employers
  ADD CONSTRAINT fk_employers_company
    FOREIGN KEY (company_id) REFERENCES companies (company_id) ON DELETE SET NULL;
