# Victory Job Portal

Victory is a PHP and MariaDB job portal for job seekers, recruiters, and administrators. It provides public job discovery, job applications, employer job management, and profile tools for job seekers.

## Features

- Public landing page, job listings, job details, and contact form.
- Registration and sign-in for job seekers and employers, plus company organization records.
- Job search with an AJAX endpoint, job applications, saved jobs, and notifications.
- Job-seeker profiles with resume/profile-photo uploads, skills, projects, and education records where supported by the profile type.
- Employer job posting and management, applicant review, and application-status updates.
- Admin tools for managing users, companies, jobs, categories, applications, and CSV imports.
- Account setup and password-reset flows that can send email when mail is configured.

## User types and permissions

The application has three authenticated roles:

| Role / registration type | What it means |
| --- | --- |
| **Student** | A job-seeker account with the `student` profile type. Student-specific academic information is supported. |
| **Employee / Experienced Professional** | A job-seeker account with the `professional` profile type. “Employee” is not a separate login role. |
| **Unclassified job seeker** | A job-seeker account using the `unclassified` profile type, with shared job-seeker features. |
| **Employer / Recruiter** | An authenticated employer account that can manage its jobs and review applicants. |
| **Company / Organization** | An organization record, not a login account. An authorized employer/recruiter account must be associated with the company to use recruiting tools. |
| **Administrator** | An authenticated admin role for administrative management tools. There is no public admin registration or first-admin setup page. |

Access to protected pages is role-checked. Employers can manage their own jobs; job seekers manage their own profile and application-related actions. Administrative routes require the admin role.

## Technology stack

- PHP (core PHP; no Composer dependency manifest)
- MariaDB / MySQL with InnoDB and `utf8mb4`
- HTML, CSS, and browser JavaScript
- Apache via XAMPP for local development
- MySQLi for database access
- Google Fonts and Font Awesome loaded from external CDNs

The project was verified with PHP 8.2 and MariaDB 10.4.32 on XAMPP. PHP extensions used by the application include MySQLi (with `get_result` support), mbstring, fileinfo, and JSON. No Node.js package manifest or front-end build step is present.

## Project structure

```text
victory/
├── admin/                 # Administrative pages and CSV import workflow
├── ajax/                  # AJAX endpoints, including job search
├── assets/
│   ├── css/               # Page and shared styles
│   ├── js/                # Browser-side behavior
│   └── images/            # Static image assets
├── auth/                  # Registration, login, reset, and account setup
├── database/
│   ├── migrations/        # One-time legacy database migrations
│   └── victory_db_schema.sql  # Current schema-only export
├── employer/              # Recruiter dashboard, jobs, and applicants
├── includes/              # Configuration, sessions, security, and helpers
├── jobseeker/             # Job-seeker dashboard and profile management
├── uploads/               # Runtime upload directories and access rules
├── .htaccess              # URL rewriting and Apache access protections
├── index.php              # Application landing page
├── jobs.php               # Public job listings
├── job-details.php        # Public job details and application actions
└── README.md
```

Uploaded files and private database exports are runtime/private data. Do not commit them. The repository `.gitignore` excludes uploads, local secrets, and the populated legacy SQL dump while preserving upload-directory `.htaccess` files.

## Prerequisites

- Windows with XAMPP (Apache, PHP, and MariaDB/MySQL).
- PHP extensions required by the app: MySQLi, mbstring, fileinfo, and JSON; enable MySQL native-driver support for `mysqli_stmt::get_result`.
- Apache `.htaccess`/rewrite support enabled.
- A database account allowed to create the `victory_db` database and its tables.
- A browser. Internet access is needed for the externally hosted Google Fonts and Font Awesome assets.

## Fresh installation with XAMPP

### 1. Place the project

Copy or clone the project to:

```text
C:\xampp\htdocs\victory
```

### 2. Start local services

Open the XAMPP Control Panel and start **Apache** and **MySQL**.

### 3. Import the current schema

The current schema-only export is [`database/victory_db_schema.sql`](database/victory_db_schema.sql). It defines the current 17-table structure and contains no row inserts.

> **Important:** Import this file only into a new, empty, disposable `victory_db`. The export includes `DROP TABLE IF EXISTS` statements and selects the database itself. Importing it into a database with tables can drop and recreate those tables. Back up any database you intend to keep; never use this as an upgrade procedure.

From **Command Prompt** (`cmd.exe`), replace `YOUR_DB_USER` with a local database account. The command prompts for its password:

```bat
"C:\xampp\mysql\bin\mysql.exe" --host=localhost --user=YOUR_DB_USER --password < "C:\xampp\htdocs\victory\database\victory_db_schema.sql"
```

For phpMyAdmin, select **Import** and import the same schema file while connected to a local database account that can create `victory_db`. Confirm the target database is new/empty first.

Do **not** apply the four migration files after importing this schema export. It already includes the results of those migrations.

### 4. Configure the application

The application reads database settings from the process environment:

| Variable | Purpose |
| --- | --- |
| `DB_HOST` | Database server hostname |
| `DB_USER` | Database account name |
| `DB_PASSWORD` | Database account password |
| `DB_NAME` | Database name; use `victory_db` for this setup |
| `APP_ENV` | Set to `development` locally to display detailed PHP errors; do not use that setting on a public deployment |

Set these variables in the Windows/Apache environment so the Apache PHP process can read them, then restart Apache. Do not put real passwords in this README, source control, or a committed configuration file. The application does not load a `.env` file.

This export is configured specifically for `victory_db`. Use that database name for the documented installation rather than importing it under a different name.

### 5. Open the application

Visit:

```text
http://localhost/victory/
```

Apache uses `index.php` as the directory index and rewrites extensionless PHP routes. If rewritten routes fail, check that Apache allows `.htaccess` overrides and has rewrite support enabled.

## Alternative: migrate an existing legacy database

This is **not** part of a fresh installation. Use it only when upgrading a backed-up legacy database that already has the original base tables expected by the Phase 2 migration. Do not combine this path with importing `victory_db_schema.sql`.

The migration order is:

1. [`20261031_phase2_management.sql`](database/migrations/20261031_phase2_management.sql)
2. [`20261031_student_education_records.sql`](database/migrations/20261031_student_education_records.sql)
3. [`20261101_student_skills.sql`](database/migrations/20261101_student_skills.sql)
4. [`20261101_job_seeker_projects.sql`](database/migrations/20261101_job_seeker_projects.sql)

Before starting:

- Make and verify a complete database backup.
- Confirm that the database is at the expected pre-migration schema level and that the migration tables/columns do not already exist.
- Apply each migration once, in the order above, stopping immediately on any error. These scripts include non-idempotent DDL and should not be blindly rerun after a partial failure.
- Verify the resulting schema and preserved/backfilled records before deploying the application.

The repository's older [`database/victory_db.sql`](database/victory_db.sql) is a populated legacy dump, not a safe schema-only baseline. **Do not import or publish it as demo data.** For a legacy installation, use the site's existing database and do not replace it with that dump.

## Run locally

1. Start Apache and MySQL from XAMPP.
2. Confirm database environment variables are available to Apache's PHP process.
3. Browse to `http://localhost/victory/`.
4. Register a job seeker or employer through the public registration page to exercise those roles.

The application does not provide a public first-admin creation flow. An administrator account must be provisioned through a separately secured operator procedure; do not expose or reuse credentials from a database dump.

Password reset and account setup email require PHP mail transport plus `APP_BASE_URL` and `APP_MAIL_FROM` configured for the local/deployed environment. Email delivery is not required for browsing public pages.

## Screenshots

Add sanitized screenshots in `docs/screenshots/`. Use fabricated data and hide email addresses, account identifiers, uploaded-file paths, reset/setup links, and other private information.

<!-- Replace these placeholders with screenshots captured from a sanitized demo environment. -->

| Page | Screenshot |
| --- | --- |
| Public home page | `docs/screenshots/home.png` |
| Job listings and details | `docs/screenshots/jobs.png` |
| Student dashboard/profile | `docs/screenshots/student-profile.png` |
| Projects and skills section | `docs/screenshots/profile-projects.png` |
| Employer dashboard and jobs | `docs/screenshots/employer-dashboard.png` |
| Applicant review | `docs/screenshots/applicants.png` |
| Admin dashboard | `docs/screenshots/admin-dashboard.png` |
| Responsive mobile view | `docs/screenshots/mobile.png` |

## Security notes

The source includes password hashing and verification, role and active-account checks, CSRF validation for state-changing forms, prepared SQL statements in data operations, owner-scoped management routes, output escaping, and session-cookie protections. Account setup/reset tokens are generated randomly and stored as hashes with expiry. Upload handling applies file-type and size checks, and Apache access rules block directory listing and direct access to private database paths.

These controls do not replace deployment hardening. Keep secrets outside source control, use HTTPS in production, restrict database privileges, configure secure mail transport, and review upload storage and Apache rules before deployment. Never commit uploaded user files, populated SQL dumps, local backups, or credentials.

## Known limitations

- No public admin registration or first-admin provisioning interface is included.
- Company registration creates an organization record, not a login; recruiters need an employer account associated with that company.
- Email setup/reset flows depend on server mail configuration.
- The project has no Composer/npm dependency manifests or documented automated test runner.
- The checked-in schema export is the current schema, while the migration scripts are for a separate one-time legacy upgrade path.
- The employer job-posting form does not currently provide category selection, even though category management exists.
- Job recommendations use existing profile/location/legacy-skill data and are not AI recommendations.
- Projects are managed on the job seeker's profile; employer/admin project visibility is not included.
- External fonts and icons depend on third-party CDNs.

## Future improvements

Potential follow-up work, not claims about current functionality:

- Add a secure, documented first-admin provisioning workflow.
- Automate clean-install and migration checks in a test suite or CI workflow.
- Provide synthetic demo data and sanitized screenshots.
- Consolidate migration tracking and improve partial-migration recovery guidance.
- Review stale front-end behavior and improve accessible responsive interactions.
- Consider richer job matching and optional employer-facing project portfolios.

## Author and contributions

Victory Job Portal is maintained by its project contributors. Add contributor names and profile links here only with their consent.

Contributions are welcome:

1. Open an issue describing the proposed change or bug.
2. Keep changes scoped and avoid committing user data, secrets, or private database exports.
3. Test the affected flow locally and document any schema changes.
4. Submit a pull request with a concise description and test results.

## License

No license file was verified in the project at the time this README was prepared. Until a license is added, do not assume the source is available for reuse or redistribution.
