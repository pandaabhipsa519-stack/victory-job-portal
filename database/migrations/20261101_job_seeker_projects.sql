-- Victory Stage 3A.4: job-seeker project records.
-- Apply once after creating a complete database backup.

CREATE TABLE job_seeker_projects (
  project_id INT(11) NOT NULL AUTO_INCREMENT,
  js_id INT(11) NOT NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  technologies TEXT NOT NULL,
  github_url VARCHAR(2048) DEFAULT NULL,
  live_demo_url VARCHAR(2048) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (project_id),
  KEY idx_job_seeker_projects_js_id (js_id),
  CONSTRAINT fk_job_seeker_projects_job_seeker
    FOREIGN KEY (js_id) REFERENCES job_seekers (js_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
