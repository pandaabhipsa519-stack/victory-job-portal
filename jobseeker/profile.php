<?php
require_once "../includes/config.php";
require_once "../includes/session.php";

requireJobseeker();

$user_id = $_SESSION['user_id'];

// get user info
$stmt = $conn->prepare("
SELECT u.email, j.*
FROM users u
JOIN job_seekers j ON u.user_id = j.user_id
WHERE u.user_id = ?
");

$stmt->bind_param("i",$user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    http_response_code(404);
    exit("Profile not found.");
}

$isStudentProfile = in_array($user['profile_type'] ?? '', ['student', 'unclassified'], true);
$jsId = (int) $user['js_id'];
$educationRecords = [];
$educationEditing = null;
$skillRecords = [];
$skillEditing = null;
$projectRecords = [];
$projectEditing = null;
$currentYear = (int) date('Y');

if ($isStudentProfile) {
    $educationStmt = $conn->prepare("
        SELECT education_id, qualification, college_university, branch,
               admission_year, passing_year, current_year_semester, cgpa, percentage
        FROM student_educations
        WHERE js_id = ?
        ORDER BY passing_year DESC, education_id DESC
    ");
    $educationStmt->bind_param("i", $jsId);
    $educationStmt->execute();
    $educationResult = $educationStmt->get_result();
    while ($education = $educationResult->fetch_assoc()) {
        $educationRecords[] = $education;
    }

    $editId = $_GET['edit_education'] ?? '';
    if (is_string($editId) && ctype_digit($editId) && (int) $editId > 0) {
        $editEducationStmt = $conn->prepare("
            SELECT education_id, qualification, college_university, branch,
                   admission_year, passing_year, current_year_semester, cgpa, percentage
            FROM student_educations
            WHERE education_id = ? AND js_id = ?
            LIMIT 1
        ");
        $editId = (int) $editId;
        $editEducationStmt->bind_param("ii", $editId, $jsId);
        $editEducationStmt->execute();
        $educationEditing = $editEducationStmt->get_result()->fetch_assoc() ?: null;
    }
}

$skillsStmt = $conn->prepare("
    SELECT skill_id, skill_name, proficiency
    FROM student_skills
    WHERE js_id = ?
    ORDER BY skill_name
");
$skillsStmt->bind_param("i", $jsId);
$skillsStmt->execute();
$skillsResult = $skillsStmt->get_result();
while ($skill = $skillsResult->fetch_assoc()) {
    $skillRecords[] = $skill;
}

$editSkillId = $_GET['edit_skill'] ?? '';
if (is_string($editSkillId) && ctype_digit($editSkillId) && (int) $editSkillId > 0) {
    $editSkillStmt = $conn->prepare("
        SELECT skill_id, skill_name, proficiency
        FROM student_skills
        WHERE skill_id = ? AND js_id = ?
        LIMIT 1
    ");
    $editSkillId = (int) $editSkillId;
    $editSkillStmt->bind_param("ii", $editSkillId, $jsId);
    $editSkillStmt->execute();
    $skillEditing = $editSkillStmt->get_result()->fetch_assoc() ?: null;
}

$projectsStmt = $conn->prepare("
    SELECT project_id, title, description, technologies, github_url, live_demo_url
    FROM job_seeker_projects
    WHERE js_id = ?
    ORDER BY created_at DESC, project_id DESC
");
$projectsStmt->bind_param("i", $jsId);
$projectsStmt->execute();
$projectsResult = $projectsStmt->get_result();
while ($project = $projectsResult->fetch_assoc()) {
    $projectRecords[] = $project;
}

$editProjectId = $_GET['edit_project'] ?? '';
if (is_string($editProjectId) && ctype_digit($editProjectId) && (int) $editProjectId > 0) {
    $editProjectStmt = $conn->prepare("
        SELECT project_id, title, description, technologies, github_url, live_demo_url
        FROM job_seeker_projects
        WHERE project_id = ? AND js_id = ?
        LIMIT 1
    ");
    $editProjectId = (int) $editProjectId;
    $editProjectStmt->bind_param("ii", $editProjectId, $jsId);
    $editProjectStmt->execute();
    $projectEditing = $editProjectStmt->get_result()->fetch_assoc() ?: null;
}
?>

<?php include "../includes/header.php"; ?>

<style>
    body:has(.student-profile-page) > .container {
        width: min(1200px, calc(100% - 48px));
    }

    .student-profile-page {
        max-width: 1040px;
        margin: 0 auto;
    }

    .student-profile-page .profile-page-heading {
        margin-bottom: 1.5rem;
    }

    .student-profile-page .profile-page-heading h1 {
        margin: 0;
        color: var(--color-ink, #172b3a);
        font-size: clamp(1.65rem, 3vw, 2.1rem);
        line-height: 1.25;
    }

    .student-profile-page .profile-page-heading p {
        margin: 0.4rem 0 0;
        color: var(--color-muted, #607181);
    }

    .student-profile-page .profile-notice {
        margin: 0 0 1.25rem;
        padding: 0.85rem 1rem;
        border: 1px solid var(--color-border, #dce5ec);
        border-radius: 0.75rem;
        background: var(--color-surface, #fff);
        color: var(--color-ink, #172b3a);
    }

    .student-profile-page .profile-notice[data-status="updated"] {
        border-color: #b7dfc8;
        background: #e9f6ef;
        color: #155d3c;
    }

    .student-profile-page .profile-notice[data-status="invalid"],
    .student-profile-page .profile-notice[data-status="save"] {
        border-color: #f0c2bd;
        background: #fff0ee;
        color: #8f2119;
    }

    .student-profile-page .profile-edit-layout {
        display: grid;
        grid-template-columns: minmax(220px, 0.72fr) minmax(0, 2fr);
        align-items: start;
        gap: 1.25rem;
    }

    .student-profile-page .profile-card {
        min-width: 0;
        padding: clamp(1.1rem, 2.5vw, 1.6rem);
        border: 1px solid var(--color-border, #dce5ec);
        border-radius: 1rem;
        background: var(--color-surface, #fff);
        box-shadow: var(--shadow-sm, 0 2px 8px rgb(23 43 58 / 6%));
    }

    .student-profile-page .photo-card {
        text-align: center;
    }

    .student-profile-page .photo-card h2,
    .student-profile-page .personal-card h2 {
        margin: 0;
        color: var(--color-ink, #172b3a);
        font-size: 1.15rem;
        line-height: 1.4;
    }

    .student-profile-page .photo-card > p {
        margin: 0.35rem 0 1.15rem;
        color: var(--color-muted, #607181);
        font-size: 0.9rem;
    }

    .student-profile-page .profile-photo {
        display: block;
        width: 116px;
        height: 116px;
        margin: 0 auto 1.1rem;
        border: 4px solid var(--color-primary-soft, #eaf3fb);
        border-radius: 50%;
        object-fit: cover;
    }

    .student-profile-page .photo-upload-form {
        display: grid;
        gap: 0.75rem;
        text-align: left;
    }

    .student-profile-page .photo-upload-form label {
        color: var(--color-ink, #172b3a);
        font-size: 0.88rem;
        font-weight: 600;
    }

    .student-profile-page .photo-upload-form input[type="file"] {
        width: 100%;
        min-width: 0;
        padding: 0.55rem;
        border: 1px solid var(--color-border, #dce5ec);
        border-radius: 0.6rem;
        background: var(--color-background, #f3f6f8);
        color: var(--color-ink, #172b3a);
        font-size: 0.82rem;
    }

    .student-profile-page .profile-button {
        display: inline-flex;
        min-height: 44px;
        align-items: center;
        justify-content: center;
        padding: 0.65rem 1rem;
        border: 1px solid var(--color-primary, #1769aa);
        border-radius: 0.65rem;
        background: var(--color-primary, #1769aa);
        color: #fff;
        font: inherit;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 160ms ease, border-color 160ms ease, transform 160ms ease;
    }

    .student-profile-page .profile-button:hover {
        border-color: var(--color-primary-dark, #0e4f86);
        background: var(--color-primary-dark, #0e4f86);
        color: #fff;
    }

    .student-profile-page .profile-button:active {
        transform: translateY(1px);
    }

    .student-profile-page .personal-card-header {
        margin-bottom: 1.25rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--color-border, #dce5ec);
    }

    .student-profile-page .personal-card-header p {
        margin: 0.35rem 0 0;
        color: var(--color-muted, #607181);
        font-size: 0.9rem;
    }

    .student-profile-page .profile-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem 1.1rem;
    }

    .student-profile-page .profile-field {
        display: grid;
        min-width: 0;
        align-content: start;
        gap: 0.4rem;
    }

    .student-profile-page .profile-field--wide {
        grid-column: 1 / -1;
    }

    .student-profile-page .profile-field label {
        color: var(--color-ink, #172b3a);
        font-size: 0.88rem;
        font-weight: 600;
    }

    .student-profile-page .profile-field input,
    .student-profile-page .profile-field select,
    .student-profile-page .profile-field textarea {
        width: 100%;
        min-width: 0;
        min-height: 46px;
        padding: 0.65rem 0.8rem;
        border: 1px solid var(--color-border, #dce5ec);
        border-radius: 0.65rem;
        background: var(--color-surface, #fff);
        color: var(--color-ink, #172b3a);
        font: inherit;
        font-size: 0.92rem;
        transition: border-color 160ms ease, box-shadow 160ms ease;
    }

    .student-profile-page .profile-field textarea {
        min-height: 108px;
        resize: vertical;
    }

    .student-profile-page .profile-field input:focus,
    .student-profile-page .profile-field select:focus,
    .student-profile-page .profile-field textarea:focus,
    .student-profile-page .photo-upload-form input:focus {
        border-color: var(--color-primary, #1769aa);
        box-shadow: var(--focus-ring, 0 0 0 3px rgb(23 105 170 / 22%));
        outline: none;
    }

    .student-profile-page .profile-field input[readonly] {
        background: var(--color-background, #f3f6f8);
        color: var(--color-muted, #607181);
    }

    .student-profile-page .profile-field small {
        color: var(--color-muted, #607181);
        font-size: 0.78rem;
        line-height: 1.5;
    }

    .student-profile-page .profile-form-actions {
        display: flex;
        justify-content: flex-end;
        margin-top: 1.25rem;
        padding-top: 1rem;
        border-top: 1px solid var(--color-border, #dce5ec);
    }

    .student-profile-page .skills-section,
    .student-profile-page .education-section {
        margin-top: 1.25rem;
    }

    .student-profile-page .skills-section-heading {
        margin-bottom: 1.25rem;
    }

    .student-profile-page .skills-section-heading h2 {
        margin: 0;
        color: var(--color-ink, #172b3a);
        font-size: 1.15rem;
    }

    .student-profile-page .skills-section-heading p {
        margin: 0.35rem 0 0;
        color: var(--color-muted, #607181);
        font-size: 0.9rem;
    }

    .student-profile-page .skill-form-card {
        margin-bottom: 1.25rem;
        padding: 1rem;
        border: 1px solid var(--color-border, #dce5ec);
        border-radius: 0.8rem;
        background: var(--color-background, #f3f6f8);
    }

    .student-profile-page .skill-form-card h3 {
        margin: 0 0 1rem;
        color: var(--color-ink, #172b3a);
        font-size: 1rem;
    }

    .student-profile-page .skill-form-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.4fr) minmax(180px, 0.8fr);
        gap: 0.9rem 1rem;
    }

    .student-profile-page .skill-field {
        display: grid;
        min-width: 0;
        align-content: start;
        gap: 0.4rem;
    }

    .student-profile-page .skill-field label {
        color: var(--color-ink, #172b3a);
        font-size: 0.85rem;
        font-weight: 600;
    }

    .student-profile-page .skill-field input,
    .student-profile-page .skill-field select {
        width: 100%;
        min-width: 0;
        min-height: 44px;
        padding: 0.6rem 0.75rem;
        border: 1px solid var(--color-border, #dce5ec);
        border-radius: 0.6rem;
        background: var(--color-surface, #fff);
        color: var(--color-ink, #172b3a);
        font: inherit;
        font-size: 0.9rem;
    }

    .student-profile-page .skill-form-actions,
    .student-profile-page .skill-record-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.65rem;
    }

    .student-profile-page .skill-form-actions {
        justify-content: flex-end;
        margin-top: 1rem;
    }

    .student-profile-page .skill-list {
        display: grid;
        gap: 0.75rem;
    }

    .student-profile-page .skill-record {
        display: flex;
        min-width: 0;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.85rem 1rem;
        border: 1px solid var(--color-border, #dce5ec);
        border-radius: 0.75rem;
        background: var(--color-surface, #fff);
    }

    .student-profile-page .skill-record-details {
        display: flex;
        min-width: 0;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.55rem 0.75rem;
    }

    .student-profile-page .skill-record-name {
        overflow-wrap: anywhere;
        color: var(--color-ink, #172b3a);
        font-weight: 600;
    }

    .student-profile-page .skill-proficiency {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.6rem;
        border: 1px solid #cfe0ee;
        border-radius: 999px;
        background: var(--color-primary-soft, #eaf3fb);
        color: var(--color-primary-dark, #0e4f86);
        font-size: 0.76rem;
        font-weight: 600;
    }

    .student-profile-page .skill-record-actions {
        flex-shrink: 0;
    }

    .student-profile-page .skill-record-actions a {
        text-decoration: none;
    }

    .student-profile-page .skill-delete-form {
        margin: 0;
    }

    .student-profile-page .skill-delete-button {
        min-height: 40px;
        padding: 0.5rem 0.75rem;
        border: 1px solid #f0c2bd;
        border-radius: 0.6rem;
        background: #fff;
        color: #8f2119;
        font: inherit;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
    }

    .student-profile-page .skill-delete-button:hover {
        background: #fff0ee;
    }

    .student-profile-page .skill-empty-state {
        margin: 0;
        padding: 1rem;
        border: 1px dashed var(--color-border, #dce5ec);
        border-radius: 0.8rem;
        color: var(--color-muted, #607181);
        text-align: center;
    }

    .student-profile-page .projects-section {
        margin-top: 1.25rem;
    }

    .student-profile-page .projects-section-heading {
        margin-bottom: 1.25rem;
    }

    .student-profile-page .projects-section-heading h2 {
        margin: 0;
        color: var(--color-ink, #172b3a);
        font-size: 1.15rem;
    }

    .student-profile-page .projects-section-heading p {
        margin: 0.35rem 0 0;
        color: var(--color-muted, #607181);
        font-size: 0.9rem;
    }

    .student-profile-page .project-form-card {
        margin-bottom: 1.25rem;
        padding: 1rem;
        border: 1px solid var(--color-border, #dce5ec);
        border-radius: 0.8rem;
        background: var(--color-background, #f3f6f8);
    }

    .student-profile-page .project-form-card h3 {
        margin: 0 0 1rem;
        color: var(--color-ink, #172b3a);
        font-size: 1rem;
    }

    .student-profile-page .project-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.9rem 1rem;
    }

    .student-profile-page .project-field {
        display: grid;
        min-width: 0;
        align-content: start;
        gap: 0.4rem;
    }

    .student-profile-page .project-field--wide {
        grid-column: 1 / -1;
    }

    .student-profile-page .project-field label {
        color: var(--color-ink, #172b3a);
        font-size: 0.85rem;
        font-weight: 600;
    }

    .student-profile-page .project-field input,
    .student-profile-page .project-field textarea {
        width: 100%;
        min-width: 0;
        min-height: 44px;
        padding: 0.6rem 0.75rem;
        border: 1px solid var(--color-border, #dce5ec);
        border-radius: 0.6rem;
        background: var(--color-surface, #fff);
        color: var(--color-ink, #172b3a);
        font: inherit;
        font-size: 0.9rem;
    }

    .student-profile-page .project-field textarea {
        min-height: 110px;
        resize: vertical;
    }

    .student-profile-page .project-form-actions,
    .student-profile-page .project-record-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.65rem;
    }

    .student-profile-page .project-form-actions {
        justify-content: flex-end;
        margin-top: 1rem;
    }

    .student-profile-page .project-list {
        display: grid;
        gap: 0.8rem;
    }

    .student-profile-page .project-record {
        display: flex;
        min-width: 0;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem;
        border: 1px solid var(--color-border, #dce5ec);
        border-radius: 0.8rem;
        background: var(--color-surface, #fff);
    }

    .student-profile-page .project-record-content {
        min-width: 0;
    }

    .student-profile-page .project-record h3 {
        margin: 0;
        color: var(--color-ink, #172b3a);
        font-size: 1rem;
        line-height: 1.45;
        overflow-wrap: anywhere;
    }

    .student-profile-page .project-record-description,
    .student-profile-page .project-record-technologies {
        margin: 0.55rem 0 0;
        color: var(--color-muted, #607181);
        font-size: 0.9rem;
        line-height: 1.6;
        overflow-wrap: anywhere;
    }

    .student-profile-page .project-record-technologies strong {
        color: var(--color-ink, #172b3a);
    }

    .student-profile-page .project-record-links {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem 1rem;
        margin-top: 0.75rem;
    }

    .student-profile-page .project-record-links a {
        color: var(--color-primary-dark, #0e4f86);
        font-size: 0.88rem;
        font-weight: 600;
        overflow-wrap: anywhere;
    }

    .student-profile-page .project-record-actions {
        flex-shrink: 0;
    }

    .student-profile-page .project-record-actions a {
        text-decoration: none;
    }

    .student-profile-page .project-delete-form {
        margin: 0;
    }

    .student-profile-page .project-delete-button {
        min-height: 40px;
        padding: 0.5rem 0.75rem;
        border: 1px solid #f0c2bd;
        border-radius: 0.6rem;
        background: #fff;
        color: #8f2119;
        font: inherit;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
    }

    .student-profile-page .project-delete-button:hover {
        background: #fff0ee;
    }

    .student-profile-page .project-empty-state {
        margin: 0;
        padding: 1rem;
        border: 1px dashed var(--color-border, #dce5ec);
        border-radius: 0.8rem;
        color: var(--color-muted, #607181);
        text-align: center;
    }

    .student-profile-page .education-section-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.25rem;
    }

    .student-profile-page .education-section-heading h2 {
        margin: 0;
        color: var(--color-ink, #172b3a);
        font-size: 1.15rem;
    }

    .student-profile-page .education-section-heading p {
        margin: 0.35rem 0 0;
        color: var(--color-muted, #607181);
        font-size: 0.9rem;
    }

    .student-profile-page .education-form-card {
        margin-bottom: 1.25rem;
        padding: 1rem;
        border: 1px solid var(--color-border, #dce5ec);
        border-radius: 0.8rem;
        background: var(--color-background, #f3f6f8);
    }

    .student-profile-page .education-form-card h3 {
        margin: 0 0 1rem;
        color: var(--color-ink, #172b3a);
        font-size: 1rem;
    }

    .student-profile-page .education-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.9rem 1rem;
    }

    .student-profile-page .education-field {
        display: grid;
        min-width: 0;
        align-content: start;
        gap: 0.4rem;
    }

    .student-profile-page .education-field--wide {
        grid-column: 1 / -1;
    }

    .student-profile-page .education-field label {
        color: var(--color-ink, #172b3a);
        font-size: 0.85rem;
        font-weight: 600;
    }

    .student-profile-page .education-field input,
    .student-profile-page .education-field select {
        width: 100%;
        min-width: 0;
        min-height: 44px;
        padding: 0.6rem 0.75rem;
        border: 1px solid var(--color-border, #dce5ec);
        border-radius: 0.6rem;
        background: var(--color-surface, #fff);
        color: var(--color-ink, #172b3a);
        font: inherit;
        font-size: 0.9rem;
    }

    .student-profile-page .education-field small {
        color: var(--color-muted, #607181);
        font-size: 0.76rem;
    }

    .student-profile-page .education-form-actions,
    .student-profile-page .education-record-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.65rem;
    }

    .student-profile-page .education-form-actions {
        justify-content: flex-end;
        margin-top: 1rem;
    }

    .student-profile-page .profile-button--secondary {
        border-color: var(--color-border, #dce5ec);
        background: var(--color-surface, #fff);
        color: var(--color-ink, #172b3a);
        text-decoration: none;
    }

    .student-profile-page .profile-button--secondary:hover {
        border-color: var(--color-primary, #1769aa);
        background: var(--color-primary-soft, #eaf3fb);
        color: var(--color-primary-dark, #0e4f86);
    }

    .student-profile-page .education-record-list {
        display: grid;
        gap: 0.8rem;
    }

    .student-profile-page .education-record {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem;
        border: 1px solid var(--color-border, #dce5ec);
        border-radius: 0.8rem;
        background: var(--color-surface, #fff);
    }

    .student-profile-page .education-record h3 {
        margin: 0;
        color: var(--color-ink, #172b3a);
        font-size: 1rem;
        line-height: 1.45;
    }

    .student-profile-page .education-record p {
        margin: 0.2rem 0 0;
        color: var(--color-muted, #607181);
        font-size: 0.88rem;
    }

    .student-profile-page .education-record-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem 0.85rem;
        margin-top: 0.65rem;
        color: var(--color-muted, #607181);
        font-size: 0.8rem;
    }

    .student-profile-page .education-record-actions {
        justify-content: flex-end;
        flex-shrink: 0;
    }

    .student-profile-page .education-record-actions a {
        text-decoration: none;
    }

    .student-profile-page .education-delete-form {
        margin: 0;
    }

    .student-profile-page .education-delete-button {
        min-height: 40px;
        padding: 0.5rem 0.75rem;
        border: 1px solid #f0c2bd;
        border-radius: 0.6rem;
        background: #fff;
        color: #8f2119;
        font: inherit;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
    }

    .student-profile-page .education-delete-button:hover {
        background: #fff0ee;
    }

    .student-profile-page .education-empty-state {
        margin: 0;
        padding: 1rem;
        border: 1px dashed var(--color-border, #dce5ec);
        border-radius: 0.8rem;
        color: var(--color-muted, #607181);
        text-align: center;
    }

    @media (max-width: 760px) {
        body:has(.student-profile-page) > .container {
            width: calc(100% - 32px);
        }

        .student-profile-page .profile-edit-layout {
            grid-template-columns: minmax(0, 1fr);
        }

        .student-profile-page .profile-form-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .student-profile-page .profile-field--wide {
            grid-column: auto;
        }

        .student-profile-page .education-form-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .student-profile-page .skill-form-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .student-profile-page .project-form-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .student-profile-page .project-field--wide {
            grid-column: auto;
        }

        .student-profile-page .skill-record {
            align-items: flex-start;
            flex-direction: column;
        }

        .student-profile-page .skill-record-actions {
            width: 100%;
        }

        .student-profile-page .education-field--wide {
            grid-column: auto;
        }

        .student-profile-page .education-record {
            flex-direction: column;
        }

        .student-profile-page .education-record-actions {
            justify-content: flex-start;
            width: 100%;
        }

        .student-profile-page .project-record {
            flex-direction: column;
        }

        .student-profile-page .project-record-actions {
            width: 100%;
        }

        .student-profile-page .photo-card {
            display: grid;
            grid-template-columns: 88px minmax(0, 1fr);
            align-items: center;
            gap: 0 1rem;
            text-align: left;
        }

        .student-profile-page .photo-card h2,
        .student-profile-page .photo-card > p {
            grid-column: 2;
        }

        .student-profile-page .photo-card h2 {
            align-self: end;
        }

        .student-profile-page .photo-card > p {
            align-self: start;
            margin: 0.2rem 0 0.65rem;
        }

        .student-profile-page .profile-photo {
            grid-column: 1;
            grid-row: 1 / span 2;
            width: 80px;
            height: 80px;
            margin: 0;
        }

        .student-profile-page .photo-upload-form {
            grid-column: 1 / -1;
            margin-top: 1rem;
        }
    }

    @media (max-width: 520px) {
        body:has(.student-profile-page) > .container {
            width: calc(100% - 24px);
        }

        .student-profile-page .profile-form-actions .profile-button {
            width: 100%;
        }
    }
</style>

<main class="student-profile-page">
<header class="profile-page-heading">
    <h1>My Profile</h1>
    <p>Keep your personal information up to date for better opportunities.</p>
</header>

<?php
$profileMessage = [
    'updated' => 'Your profile information has been updated.',
    'invalid' => 'Please review the profile information and correct any invalid or overlong fields.',
    'save' => 'Your profile could not be updated. Please try again later.',
];
$messageType = $_GET['status'] ?? '';
if (is_string($messageType) && isset($profileMessage[$messageType])):
?>
<p class="profile-notice" data-status="<?php echo e($messageType); ?>" role="status" aria-live="polite"><?php echo e($profileMessage[$messageType]); ?></p>
<?php endif; ?>

<div class="profile-edit-layout">

<aside class="profile-card photo-card" aria-labelledby="profile-photo-heading">

<?php
$profile = "../assets/images/default-user.png";

if(!empty($user['profile_pic'])){
    $profile = "../uploads/profile/" . basename((string) $user['profile_pic']);
}
?>

<img class="profile-photo" src="<?php echo e($profile); ?>" alt="Your profile photo">
<h2 id="profile-photo-heading">Profile photo</h2>
<p>Use a clear, professional photo.</p>

<form class="photo-upload-form" method="POST" enctype="multipart/form-data" action="update-profile-pic.php">
<?php echo csrfInput(); ?>
<label for="profile-picture-file">Choose a new photo</label>
<input id="profile-picture-file" type="file" name="profile_pic" accept="image/jpeg,image/png,image/webp" required>
<button class="profile-button" type="submit">Update Photo</button>
</form>

</aside>

<section class="profile-card personal-card" aria-labelledby="personal-information-heading">
<div class="personal-card-header">
    <h2 id="personal-information-heading">Personal Information</h2>
    <p>Update your contact details and profile summary.</p>
</div>

<form method="POST" action="update_profile.php">
<?php echo csrfInput(); ?>

<div class="profile-form-grid">
<div class="profile-field">
<label for="profile-name">Full name</label>
<input id="profile-name" type="text" name="name" maxlength="100" autocomplete="name" required
       value="<?php echo e($user['name']); ?>">
</div>

<div class="profile-field">
<label for="profile-email">Login email</label>
<input id="profile-email" type="email" value="<?php echo e($user['email']); ?>" readonly>
<small>Login email cannot be changed here.</small>
</div>

<div class="profile-field">
<label for="profile-phone">Phone</label>
<input id="profile-phone" type="tel" name="phone" maxlength="15" autocomplete="tel"
       value="<?php echo e($user['phone']); ?>">
</div>

<div class="profile-field">
<label for="profile-gender">Gender</label>
<select id="profile-gender" name="gender">
<option value=""<?php echo $user['gender'] === null || $user['gender'] === '' ? ' selected' : ''; ?>>Prefer not to say</option>
<option value="Male"<?php echo $user['gender'] === 'Male' ? ' selected' : ''; ?>>Male</option>
<option value="Female"<?php echo $user['gender'] === 'Female' ? ' selected' : ''; ?>>Female</option>
<option value="Other"<?php echo $user['gender'] === 'Other' ? ' selected' : ''; ?>>Other</option>
</select>
</div>

<div class="profile-field">
<label for="profile-dob">Date of birth</label>
<input id="profile-dob" type="date" name="dob" autocomplete="bday" value="<?php echo e($user['dob']); ?>">
</div>

<div class="profile-field">
<label for="profile-education">Education summary</label>
<input id="profile-education" type="text" name="education" maxlength="100"
       value="<?php echo e($user['education']); ?>">
</div>

<div class="profile-field">
<label for="profile-state">State</label>
<input id="profile-state" type="text" name="state" maxlength="100" autocomplete="address-level1"
       value="<?php echo e($user['state']); ?>">
</div>

<div class="profile-field">
<label for="profile-location">Location</label>
<input id="profile-location" type="text" name="location" maxlength="100" autocomplete="address-level2"
       value="<?php echo e($user['location']); ?>">
</div>

<div class="profile-field profile-field--wide">
<label for="profile-about">About</label>
<textarea id="profile-about" name="about" maxlength="10000" rows="5"><?php echo e($user['about']); ?></textarea>
</div>
</div>

<div class="profile-form-actions">
<button class="profile-button" type="submit">Save Changes</button>
</div>

</form>

</section>

</div>

<?php
$skillMessages = [
    'saved' => 'The skill has been saved.',
    'deleted' => 'The skill has been deleted.',
    'duplicate' => 'You already have a skill with that name.',
    'not_found' => 'That skill could not be found.',
    'invalid' => 'Please enter a valid skill name and proficiency.',
    'save' => 'The skill could not be saved. Please try again later.',
];
$skillStatus = $_GET['skill_status'] ?? '';
$skillFormProficiency = $skillEditing['proficiency'] ?? '';
?>
<section class="profile-card skills-section" id="skills-section" aria-labelledby="skills-heading">
    <div class="skills-section-heading">
        <h2 id="skills-heading">Skills</h2>
        <p>List your skills and indicate your current proficiency.</p>
    </div>

    <?php if (is_string($skillStatus) && isset($skillMessages[$skillStatus])): ?>
        <p class="profile-notice" data-status="<?php echo in_array($skillStatus, ['saved', 'deleted'], true) ? 'updated' : 'invalid'; ?>"
           role="status" aria-live="polite"><?php echo e($skillMessages[$skillStatus]); ?></p>
    <?php endif; ?>

    <div class="skill-form-card" id="skill-record-form">
        <h3><?php echo $skillEditing ? 'Edit skill' : 'Add a skill'; ?></h3>
        <form method="POST" action="skill-action.php">
            <?php echo csrfInput(); ?>
            <input type="hidden" name="action" value="<?php echo $skillEditing ? 'edit' : 'add'; ?>">
            <?php if ($skillEditing): ?>
                <input type="hidden" name="skill_id" value="<?php echo (int) $skillEditing['skill_id']; ?>">
            <?php endif; ?>

            <div class="skill-form-grid">
                <div class="skill-field">
                    <label for="skill-name">Skill</label>
                    <input id="skill-name" type="text" name="skill_name" maxlength="191"
                           autocomplete="off" required
                           value="<?php echo e($skillEditing['skill_name'] ?? ''); ?>">
                </div>
                <div class="skill-field">
                    <label for="skill-proficiency">Proficiency</label>
                    <select id="skill-proficiency" name="proficiency" required>
                        <option value="">Select proficiency</option>
                        <?php foreach (['Beginner', 'Intermediate', 'Advanced'] as $proficiency): ?>
                            <option value="<?php echo e($proficiency); ?>"
                                <?php echo $skillFormProficiency === $proficiency ? 'selected' : ''; ?>>
                                <?php echo e($proficiency); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="skill-form-actions">
                <?php if ($skillEditing): ?>
                    <a class="profile-button profile-button--secondary" href="profile.php#skills-section">Cancel</a>
                <?php endif; ?>
                <button class="profile-button" type="submit">
                    <?php echo $skillEditing ? 'Save Skill' : 'Add Skill'; ?>
                </button>
            </div>
        </form>
    </div>

    <?php if ($skillRecords): ?>
        <div class="skill-list" aria-label="Your saved skills">
            <?php foreach ($skillRecords as $skill): ?>
                <article class="skill-record">
                    <div class="skill-record-details">
                        <span class="skill-record-name"><?php echo e($skill['skill_name']); ?></span>
                        <?php if ($skill['proficiency'] !== null): ?>
                            <span class="skill-proficiency"><?php echo e($skill['proficiency']); ?></span>
                        <?php else: ?>
                            <span class="skill-proficiency">Proficiency not set</span>
                        <?php endif; ?>
                    </div>
                    <div class="skill-record-actions">
                        <a class="profile-button profile-button--secondary"
                           href="profile.php?edit_skill=<?php echo (int) $skill['skill_id']; ?>#skill-record-form">Edit</a>
                        <form class="skill-delete-form" method="POST" action="skill-action.php"
                              onsubmit="return confirm('Delete this skill?');">
                            <?php echo csrfInput(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="skill_id" value="<?php echo (int) $skill['skill_id']; ?>">
                            <button class="skill-delete-button" type="submit">Delete</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="skill-empty-state">No skills added yet. Add your first skill above.</p>
    <?php endif; ?>
</section>

<?php
$projectMessages = [
    'saved' => 'The project has been saved.',
    'deleted' => 'The project has been deleted.',
    'not_found' => 'That project could not be found.',
    'invalid' => 'Please review the project fields and URLs.',
    'save' => 'The project could not be saved. Please try again later.',
];
$projectStatus = $_GET['project_status'] ?? '';
?>
<section class="profile-card projects-section" id="projects-section" aria-labelledby="projects-heading">
    <div class="projects-section-heading">
        <h2 id="projects-heading">Projects</h2>
        <p>Showcase projects you have built and the technologies you used.</p>
    </div>

    <?php if (is_string($projectStatus) && isset($projectMessages[$projectStatus])): ?>
        <p class="profile-notice" data-status="<?php echo in_array($projectStatus, ['saved', 'deleted'], true) ? 'updated' : 'invalid'; ?>"
           role="status" aria-live="polite"><?php echo e($projectMessages[$projectStatus]); ?></p>
    <?php endif; ?>

    <div class="project-form-card" id="project-record-form">
        <h3><?php echo $projectEditing ? 'Edit project' : 'Add a project'; ?></h3>
        <form method="POST" action="project-action.php">
            <?php echo csrfInput(); ?>
            <input type="hidden" name="action" value="<?php echo $projectEditing ? 'edit' : 'add'; ?>">
            <?php if ($projectEditing): ?>
                <input type="hidden" name="project_id" value="<?php echo (int) $projectEditing['project_id']; ?>">
            <?php endif; ?>

            <div class="project-form-grid">
                <div class="project-field project-field--wide">
                    <label for="project-title">Project title</label>
                    <input id="project-title" type="text" name="title" maxlength="150" required
                           value="<?php echo e($projectEditing['title'] ?? ''); ?>">
                </div>
                <div class="project-field project-field--wide">
                    <label for="project-description">Project description</label>
                    <textarea id="project-description" name="description" maxlength="10000" rows="5" required><?php echo e($projectEditing['description'] ?? ''); ?></textarea>
                </div>
                <div class="project-field project-field--wide">
                    <label for="project-technologies">Technologies used</label>
                    <textarea id="project-technologies" name="technologies" maxlength="2000" rows="3" required><?php echo e($projectEditing['technologies'] ?? ''); ?></textarea>
                </div>
                <div class="project-field">
                    <label for="project-github-url">GitHub repository URL <span>(optional)</span></label>
                    <input id="project-github-url" type="url" name="github_url" maxlength="2048"
                           placeholder="https://github.com/..." value="<?php echo e($projectEditing['github_url'] ?? ''); ?>">
                </div>
                <div class="project-field">
                    <label for="project-live-demo-url">Live demo URL <span>(optional)</span></label>
                    <input id="project-live-demo-url" type="url" name="live_demo_url" maxlength="2048"
                           placeholder="https://..." value="<?php echo e($projectEditing['live_demo_url'] ?? ''); ?>">
                </div>
            </div>

            <div class="project-form-actions">
                <?php if ($projectEditing): ?>
                    <a class="profile-button profile-button--secondary" href="profile.php#projects-section">Cancel</a>
                <?php endif; ?>
                <button class="profile-button" type="submit">
                    <?php echo $projectEditing ? 'Save Project' : 'Add Project'; ?>
                </button>
            </div>
        </form>
    </div>

    <?php if ($projectRecords): ?>
        <div class="project-list" aria-label="Your saved projects">
            <?php foreach ($projectRecords as $project): ?>
                <article class="project-record">
                    <div class="project-record-content">
                        <h3><?php echo e($project['title']); ?></h3>
                        <p class="project-record-description"><?php echo nl2br(e($project['description'])); ?></p>
                        <p class="project-record-technologies"><strong>Technologies:</strong>
                            <?php echo nl2br(e($project['technologies'])); ?>
                        </p>
                        <?php if (!empty($project['github_url']) || !empty($project['live_demo_url'])): ?>
                            <div class="project-record-links">
                                <?php if (!empty($project['github_url'])): ?>
                                    <a href="<?php echo e($project['github_url']); ?>" target="_blank" rel="noopener noreferrer">GitHub repository</a>
                                <?php endif; ?>
                                <?php if (!empty($project['live_demo_url'])): ?>
                                    <a href="<?php echo e($project['live_demo_url']); ?>" target="_blank" rel="noopener noreferrer">Live demo</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="project-record-actions">
                        <a class="profile-button profile-button--secondary"
                           href="profile.php?edit_project=<?php echo (int) $project['project_id']; ?>#project-record-form">Edit</a>
                        <form class="project-delete-form" method="POST" action="project-action.php"
                              onsubmit="return confirm('Delete this project? This cannot be undone.');">
                            <?php echo csrfInput(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="project_id" value="<?php echo (int) $project['project_id']; ?>">
                            <button class="project-delete-button" type="submit">Delete</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="project-empty-state">No projects added yet. Add your first project above.</p>
    <?php endif; ?>
</section>

<?php if ($isStudentProfile): ?>
<?php
$educationMessages = [
    'saved' => 'The education record has been saved.',
    'deleted' => 'The education record has been deleted.',
    'not_found' => 'That education record could not be found.',
    'invalid' => 'Please check the education details and try again.',
    'save' => 'The education record could not be saved. Please try again later.',
];
$educationStatus = $_GET['education_status'] ?? '';
$editingGradeType = '';
$editingGradeValue = '';
if ($educationEditing) {
    if ($educationEditing['cgpa'] !== null) {
        $editingGradeType = 'cgpa';
        $editingGradeValue = (string) $educationEditing['cgpa'];
    } elseif ($educationEditing['percentage'] !== null) {
        $editingGradeType = 'percentage';
        $editingGradeValue = (string) $educationEditing['percentage'];
    }
}
?>
<section class="profile-card education-section" id="education-section" aria-labelledby="education-heading">
    <div class="education-section-heading">
        <div>
            <h2 id="education-heading">Education</h2>
            <p>Add your qualifications, from diplomas to postgraduate degrees.</p>
        </div>
    </div>

    <?php if (is_string($educationStatus) && isset($educationMessages[$educationStatus])): ?>
        <p class="profile-notice" data-status="<?php echo in_array($educationStatus, ['saved', 'deleted'], true) ? 'updated' : 'invalid'; ?>"
           role="status" aria-live="polite"><?php echo e($educationMessages[$educationStatus]); ?></p>
    <?php endif; ?>

    <div class="education-form-card" id="education-record-form">
        <h3><?php echo $educationEditing ? 'Edit education' : 'Add education'; ?></h3>
        <form method="POST" action="education-action.php">
            <?php echo csrfInput(); ?>
            <input type="hidden" name="action" value="<?php echo $educationEditing ? 'edit' : 'add'; ?>">
            <?php if ($educationEditing): ?>
                <input type="hidden" name="education_id" value="<?php echo (int) $educationEditing['education_id']; ?>">
            <?php endif; ?>

            <div class="education-form-grid">
                <div class="education-field">
                    <label for="education-qualification">Qualification / Degree</label>
                    <input id="education-qualification" type="text" name="qualification" maxlength="150"
                           placeholder="e.g. Diploma, BTech, BCA, MBA" required
                           value="<?php echo e($educationEditing['qualification'] ?? ''); ?>">
                </div>

                <div class="education-field">
                    <label for="education-college">College / University</label>
                    <input id="education-college" type="text" name="college_university" maxlength="180" required
                           value="<?php echo e($educationEditing['college_university'] ?? ''); ?>">
                </div>

                <div class="education-field education-field--wide">
                    <label for="education-branch">Branch / Specialization</label>
                    <input id="education-branch" type="text" name="branch" maxlength="150" required
                           value="<?php echo e($educationEditing['branch'] ?? ''); ?>">
                </div>

                <div class="education-field">
                    <label for="education-admission-year">Admission year</label>
                    <input id="education-admission-year" type="number" name="admission_year" min="1950"
                           max="<?php echo $currentYear + 10; ?>" step="1" required
                           value="<?php echo e($educationEditing['admission_year'] ?? ''); ?>">
                </div>

                <div class="education-field">
                    <label for="education-passing-year">Passing year</label>
                    <input id="education-passing-year" type="number" name="passing_year" min="1950"
                           max="<?php echo $currentYear + 10; ?>" step="1" required
                           value="<?php echo e($educationEditing['passing_year'] ?? ''); ?>">
                </div>

                <div class="education-field">
                    <label for="education-current-year">Current year / Semester</label>
                    <input id="education-current-year" type="text" name="current_year_semester" maxlength="50"
                           placeholder="e.g. 3rd Year / Semester 5 or Completed" required
                           value="<?php echo e($educationEditing['current_year_semester'] ?? ''); ?>">
                </div>

                <div class="education-field">
                    <label for="education-grade-type">Grade type</label>
                    <select id="education-grade-type" name="grade_type" required>
                        <option value=""<?php echo $editingGradeType === '' ? ' selected' : ''; ?>>Select grade type</option>
                        <option value="cgpa"<?php echo $editingGradeType === 'cgpa' ? ' selected' : ''; ?>>CGPA (out of 10)</option>
                        <option value="percentage"<?php echo $editingGradeType === 'percentage' ? ' selected' : ''; ?>>Percentage</option>
                    </select>
                </div>

                <div class="education-field">
                    <label for="education-grade-value">CGPA or Percentage</label>
                    <input id="education-grade-value" type="number" name="grade_value" min="0" max="100"
                           step="0.01" required value="<?php echo e($editingGradeValue); ?>">
                    <small>CGPA must be out of 10; percentage must be out of 100.</small>
                </div>
            </div>

            <div class="education-form-actions">
                <?php if ($educationEditing): ?>
                    <a class="profile-button profile-button--secondary" href="profile.php#education-section">Cancel</a>
                <?php endif; ?>
                <button class="profile-button" type="submit">
                    <?php echo $educationEditing ? 'Save Education' : 'Add Education'; ?>
                </button>
            </div>
        </form>
    </div>

    <?php if ($educationRecords): ?>
        <div class="education-record-list" aria-label="Your education records">
            <?php foreach ($educationRecords as $education): ?>
                <article class="education-record">
                    <div>
                        <h3><?php echo e($education['qualification'] ?: 'Education record'); ?></h3>
                        <p><?php echo e($education['college_university'] ?: 'Institution not provided'); ?></p>
                        <?php if (!empty($education['branch'])): ?>
                            <p><?php echo e($education['branch']); ?></p>
                        <?php endif; ?>
                        <div class="education-record-meta">
                            <?php if ($education['admission_year'] !== null || $education['passing_year'] !== null): ?>
                                <span>
                                    <?php echo $education['admission_year'] !== null ? (int) $education['admission_year'] : '—'; ?>
                                    –
                                    <?php echo $education['passing_year'] !== null ? (int) $education['passing_year'] : '—'; ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($education['current_year_semester'])): ?>
                                <span><?php echo e($education['current_year_semester']); ?></span>
                            <?php endif; ?>
                            <?php if ($education['cgpa'] !== null): ?>
                                <span>CGPA: <?php echo e(number_format((float) $education['cgpa'], 2)); ?>/10</span>
                            <?php elseif ($education['percentage'] !== null): ?>
                                <span>Percentage: <?php echo e(number_format((float) $education['percentage'], 2)); ?>%</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="education-record-actions">
                        <a class="profile-button profile-button--secondary"
                           href="profile.php?edit_education=<?php echo (int) $education['education_id']; ?>#education-record-form">Edit</a>
                        <form class="education-delete-form" method="POST" action="education-action.php"
                              onsubmit="return confirm('Delete this education record?');">
                            <?php echo csrfInput(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="education_id" value="<?php echo (int) $education['education_id']; ?>">
                            <button class="education-delete-button" type="submit">Delete</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="education-empty-state">No education records yet. Add your first qualification above.</p>
    <?php endif; ?>
</section>
<?php endif; ?>
</main>

<?php include "../includes/footer.php"; ?>