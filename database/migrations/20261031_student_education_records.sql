-- Victory Stage 3A.2: multiple student education records.
-- Take a database backup before applying. This migration is additive and
-- copies legacy student profile data without deleting or changing its source.

CREATE TABLE student_educations (
  education_id INT(11) NOT NULL AUTO_INCREMENT,
  js_id INT(11) NOT NULL,
  qualification VARCHAR(150) DEFAULT NULL,
  college_university VARCHAR(180) DEFAULT NULL,
  branch VARCHAR(150) DEFAULT NULL,
  admission_year SMALLINT UNSIGNED DEFAULT NULL,
  passing_year SMALLINT UNSIGNED DEFAULT NULL,
  current_year_semester VARCHAR(50) DEFAULT NULL,
  cgpa DECIMAL(5,2) DEFAULT NULL,
  percentage DECIMAL(5,2) DEFAULT NULL,
  PRIMARY KEY (education_id),
  KEY idx_student_educations_js_id (js_id),
  CONSTRAINT fk_student_educations_job_seeker
    FOREIGN KEY (js_id) REFERENCES job_seekers (js_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO student_educations
  (js_id, qualification, college_university, branch,
   passing_year, current_year_semester)
SELECT sp.js_id, NULLIF(TRIM(sp.course), ''),
       NULLIF(TRIM(sp.college_university), ''),
       NULLIF(TRIM(sp.branch), ''),
       sp.graduation_year,
       NULLIF(TRIM(sp.current_year_semester), '')
FROM student_profiles AS sp;
