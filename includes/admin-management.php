<?php
require_once __DIR__ . "/account-setup.php";

function managementEmail($value)
{
    $value = trim((string) $value);
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function legacyEducationSummary($value)
{
    $value = trim((string) $value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, 100, 'UTF-8');
    }
    $value = substr($value, 0, 100);
    while ($value !== '' && !preg_match('//u', $value)) {
        $value = substr($value, 0, -1);
    }
    return $value;
}

function validateManagementData($type, array $data)
{
    $errors = [];
    if (!in_array($type, ['student', 'professional', 'employer', 'company'], true)) {
        return ['Invalid account type.'];
    }
    $required = static function ($key, $label) use (&$errors, $data) {
        if (!isset($data[$key]) || trim((string) $data[$key]) === '') {
            $errors[] = $label . ' is required.';
        }
    };
    $text = static function ($key, $label, $max) use (&$errors, $data) {
        $value = (string) ($data[$key] ?? '');
        if (strlen($value) > $max) {
            $errors[] = $label . ' is too long.';
        }
    };

    if ($type === 'student' || $type === 'professional' || $type === 'employer') {
        $required('email', 'Email');
        if (!filter_var(managementEmail($data['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        $text('email', 'Email', 100);
    }

    if ($type === 'student') {
        $required('name', 'Full name');
        $required('college_university', 'College or university');
        $required('course', 'Course');
        $required('branch', 'Branch');
        $required('current_year_semester', 'Current year or semester');
        $required('graduation_year', 'Graduation year');
        $required('phone', 'Phone');
        $required('skills', 'Skills');
        $required('location', 'Location');
        $text('name', 'Full name', 100);
        $text('college_university', 'College or university', 180);
        $text('course', 'Course', 150);
        $text('branch', 'Branch', 150);
        $text('current_year_semester', 'Current year or semester', 50);
        $year = filter_var($data['graduation_year'] ?? null, FILTER_VALIDATE_INT);
        if ($year === false || $year < 1950 || $year > ((int) date('Y') + 10)) {
            $errors[] = 'Enter a valid graduation year.';
        }
        $text('phone', 'Phone', 20);
        $text('skills', 'Skills', 5000);
        $text('location', 'Location', 100);
    } elseif ($type === 'professional') {
        $required('name', 'Full name');
        $required('highest_qualification', 'Highest qualification');
        $required('experience_years', 'Experience');
        $required('phone', 'Phone');
        $required('skills', 'Skills');
        $required('location', 'Location');
        $text('name', 'Full name', 100);
        $text('highest_qualification', 'Highest qualification', 150);
        $text('current_previous_company', 'Current or previous company', 180);
        $experience = filter_var($data['experience_years'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($experience === false || $experience < 0 || $experience > 999.99) {
            $errors[] = 'Experience must be between 0 and 999.99 years.';
        }
        $salary = trim((string) ($data['expected_salary'] ?? ''));
        if ($salary !== '' && (!is_numeric($salary) || (float) $salary < 0 || (float) $salary > 9999999999.99)) {
            $errors[] = 'Enter a valid expected salary.';
        }
        $text('phone', 'Phone', 20);
        $text('skills', 'Skills', 5000);
        $text('location', 'Location', 100);
    } elseif ($type === 'employer') {
        $required('recruiter_name', 'Recruiter name');
        $required('designation', 'Designation');
        $required('phone', 'Phone');
        $text('recruiter_name', 'Recruiter name', 100);
        $text('designation', 'Designation', 100);
        $text('phone', 'Phone', 20);
        if (empty($data['company_id']) && trim((string) ($data['company_name'] ?? '')) === '') {
            $errors[] = 'Company association is required.';
        } elseif (!empty($data['company_id'])
            && filter_var($data['company_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $errors[] = 'Select a valid company.';
        }
        $text('company_name', 'Company name', 150);
    } elseif ($type === 'company') {
        $required('company_name', 'Company name');
        $required('phone', 'Phone');
        $text('company_name', 'Company name', 150);
        $text('industry', 'Industry', 100);
        $text('website', 'Website', 255);
        $text('official_email', 'Official email', 100);
        $text('phone', 'Phone', 20);
        $text('location', 'Location', 100);
        $text('description', 'Description', 10000);
        $website = trim((string) ($data['website'] ?? ''));
        if ($website !== '' && (!filter_var($website, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($website, PHP_URL_SCHEME)), ['http', 'https'], true))) {
            $errors[] = 'Enter a valid HTTP or HTTPS website URL.';
        }
        $officialEmail = trim((string) ($data['official_email'] ?? ''));
        if ($officialEmail !== '' && !filter_var($officialEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid official email address.';
        }
    }

    foreach (['phone'] as $field) {
        $phone = trim((string) ($data[$field] ?? ''));
        if ($phone !== '' && (
            !preg_match('/\A[0-9+(). -]{7,20}\z/', $phone)
            || preg_match_all('/[0-9]/', $phone) < 7
        )) {
            $errors[] = 'Enter a valid phone number.';
        }
    }

    return array_values(array_unique($errors));
}

function managementEmailExists($conn, $email, $exceptUserId = null)
{
    $normalized = managementEmail($email);
    if ($exceptUserId === null) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $normalized);
    } else {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id <> ? LIMIT 1");
        $stmt->bind_param("si", $normalized, $exceptUserId);
    }
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_assoc();
}

function createManagedAccount($conn, $type, array $data)
{
    if (!in_array($type, ['student', 'professional', 'employer'], true)) {
        throw new InvalidArgumentException("Invalid account type.");
    }
    $role = $type === 'employer' ? 'employer' : 'jobseeker';
    $email = managementEmail($data['email']);
    $temporaryHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $status = 'pending_setup';
    $user = $conn->prepare("INSERT INTO users (email, password, role, account_status) VALUES (?, ?, ?, ?)");
    $user->bind_param("ssss", $email, $temporaryHash, $role, $status);
    $user->execute();
    $userId = (int) $user->insert_id;
    $token = createAccountSetupToken($conn, $userId);

    if ($type === 'student' || $type === 'professional') {
        $name = trim((string) $data['name']);
        $phone = trim((string) ($data['phone'] ?? ''));
        $skills = trim((string) ($data['skills'] ?? ''));
        $location = trim((string) ($data['location'] ?? ''));
        $profileType = $type;
        $education = legacyEducationSummary($type === 'student'
            ? trim((string) $data['course']) . ' - ' . trim((string) $data['branch'])
            : trim((string) $data['highest_qualification']));
        $profile = $conn->prepare("
            INSERT INTO job_seekers (user_id, profile_type, name, phone, skills, location, education)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $profile->bind_param("issssss", $userId, $profileType, $name, $phone, $skills, $location, $education);
        $profile->execute();
        $jsId = (int) $profile->insert_id;

        if ($type === 'student') {
            $college = trim((string) $data['college_university']);
            $course = trim((string) $data['course']);
            $branch = trim((string) $data['branch']);
            $currentYear = trim((string) $data['current_year_semester']);
            $graduationYear = (int) $data['graduation_year'];
            $subtype = $conn->prepare("
                INSERT INTO student_profiles
                    (js_id, college_university, course, branch, current_year_semester, graduation_year)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $subtype->bind_param("issssi", $jsId, $college, $course, $branch, $currentYear, $graduationYear);
            $subtype->execute();
        } else {
            $qualification = trim((string) $data['highest_qualification']);
            $company = trim((string) ($data['current_previous_company'] ?? ''));
            $experience = (float) $data['experience_years'];
            $salary = trim((string) ($data['expected_salary'] ?? ''));
            $salaryValue = $salary === '' ? null : (float) $salary;
            $subtype = $conn->prepare("
                INSERT INTO professional_profiles
                    (js_id, highest_qualification, current_previous_company, experience_years, expected_salary)
                VALUES (?, ?, ?, ?, ?)
            ");
            $subtype->bind_param("issdd", $jsId, $qualification, $company, $experience, $salaryValue);
            $subtype->execute();
        }
    } else {
        $companyId = (int) $data['company_id'];
        $companyStmt = $conn->prepare("
            SELECT company_name, industry, location, description
            FROM companies WHERE company_id = ? AND status = 'active' LIMIT 1
        ");
        $companyStmt->bind_param("i", $companyId);
        $companyStmt->execute();
        $company = $companyStmt->get_result()->fetch_assoc();
        if (!$company) {
            throw new RuntimeException("Select an active company.");
        }
        $recruiterName = trim((string) $data['recruiter_name']);
        $phone = trim((string) ($data['phone'] ?? ''));
        $designation = trim((string) $data['designation']);
        $profile = $conn->prepare("
            INSERT INTO employers
                (user_id, company_name, industry, location, about, recruiter_name, phone, designation, company_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $profile->bind_param(
            "isssssssi",
            $userId,
            $company['company_name'],
            $company['industry'],
            $company['location'],
            $company['description'],
            $recruiterName,
            $phone,
            $designation,
            $companyId
        );
        $profile->execute();
    }

    writeAdminAudit($conn, 'create', $type, $userId, 'Admin created a pending account', ['role' => $role]);
    return ['user_id' => $userId, 'setup_token' => $token, 'email' => $email];
}

function createManagedCompany($conn, array $data)
{
    $companyName = trim((string) $data['company_name']);
    $industry = trim((string) ($data['industry'] ?? ''));
    $website = trim((string) ($data['website'] ?? ''));
    $email = trim((string) ($data['official_email'] ?? ''));
    $phone = trim((string) ($data['phone'] ?? ''));
    $location = trim((string) ($data['location'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    $stmt = $conn->prepare("
        INSERT INTO companies
            (company_name, industry, website, official_email, phone, location, description)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssssss", $companyName, $industry, $website, $email, $phone, $location, $description);
    $stmt->execute();
    $companyId = (int) $stmt->insert_id;
    writeAdminAudit($conn, 'create', 'company', $companyId, 'Admin created a company record');
    return $companyId;
}

function releaseCompanyManagementLock($conn)
{
    try {
        $conn->query("SELECT RELEASE_LOCK('victory_phase2_company_management')");
    } catch (Throwable $exception) {
        error_log("Company-management lock release failed: " . $exception->getMessage());
    }
}

function csvHeadersForType($type)
{
    $headers = [
        'student' => ['email', 'name', 'phone', 'college_university', 'course', 'branch', 'current_year_semester', 'graduation_year', 'skills', 'location'],
        'professional' => ['email', 'name', 'phone', 'highest_qualification', 'current_previous_company', 'experience_years', 'expected_salary', 'skills', 'location'],
        'employer' => ['email', 'recruiter_name', 'phone', 'company_name', 'designation'],
        'company' => ['company_name', 'industry', 'website', 'official_email', 'phone', 'location', 'description'],
    ];
    if (!isset($headers[$type])) {
        throw new InvalidArgumentException("Invalid import type.");
    }
    return $headers[$type];
}

function rowIsFormulaLike(array $row)
{
    foreach ($row as $field => $value) {
        if (!is_string($value)) {
            continue;
        }
        if ($field === 'phone' && preg_match('/\A\+?[0-9+(). -]+\z/', $value)) {
            continue;
        }
        if (preg_match('/\A[\x00-\x20]*[=+\-@]/', $value)) {
            return true;
        }
    }
    return false;
}
