-- Victory Stage 3A.3: normalized job-seeker skills with proficiency.
-- Backfills each nonempty legacy skills value as one unchanged entry.
-- Apply once after creating a complete database backup.

CREATE TABLE student_skills (
  skill_id INT(11) NOT NULL AUTO_INCREMENT,
  js_id INT(11) NOT NULL,
  skill_name VARCHAR(191)
    CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  skill_name_key VARCHAR(191)
    CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  proficiency ENUM('Beginner','Intermediate','Advanced') DEFAULT NULL,
  PRIMARY KEY (skill_id),
  UNIQUE KEY uq_student_skills_owner_name (js_id, skill_name_key),
  CONSTRAINT fk_student_skills_job_seeker
    FOREIGN KEY (js_id) REFERENCES job_seekers (js_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO student_skills (js_id, skill_name, skill_name_key, proficiency)
SELECT js.js_id,
       js.skills,
       LOWER(TRIM(js.skills)),
       NULL
FROM job_seekers AS js
WHERE js.skills IS NOT NULL
  AND OCTET_LENGTH(js.skills) > 0;
