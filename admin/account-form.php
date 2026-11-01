<?php
require_once __DIR__ . "/../includes/session.php";
requireAdmin();
require_once __DIR__ . "/../includes/admin-management.php";

$type = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
if (!in_array($type, ['student', 'professional', 'employer'], true)) {
    http_response_code(400);
    exit("Invalid account type.");
}
$userId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$editing = $userId !== false && $userId !== null;
$error = '';
$notice = '';
$profile = null;
$student = null;
$professional = null;

if ($editing) {
    if ($type === 'employer') {
        $stmt = $conn->prepare("
            SELECT u.user_id, u.email, u.role, u.account_status, e.emp_id, e.recruiter_name,
                   e.phone, e.designation, e.company_id
            FROM users u JOIN employers e ON e.user_id = u.user_id
            WHERE u.user_id = ? AND u.role = 'employer' LIMIT 1
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT u.user_id, u.email, u.role, u.account_status, js.js_id, js.profile_type,
                   js.name, js.phone, js.skills, js.location, js.education
            FROM users u JOIN job_seekers js ON js.user_id = u.user_id
            WHERE u.user_id = ? AND u.role = 'jobseeker' LIMIT 1
        ");
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    if (!$profile) {
        http_response_code(404);
        exit("Account not found.");
    }
    if ($type !== 'employer' && !in_array($profile['profile_type'], ['unclassified', $type], true)) {
        http_response_code(409);
        exit("This job-seeker profile has a different type. Changing types requires a separate data-preserving workflow.");
    }
    if ($type === 'student' && $profile['profile_type'] === 'student') {
        $sub = $conn->prepare("SELECT * FROM student_profiles WHERE js_id = ?");
        $sub->bind_param("i", $profile['js_id']);
        $sub->execute();
        $student = $sub->get_result()->fetch_assoc();
    } elseif ($type === 'professional' && $profile['profile_type'] === 'professional') {
        $sub = $conn->prepare("SELECT * FROM professional_profiles WHERE js_id = ?");
        $sub->bind_param("i", $profile['js_id']);
        $sub->execute();
        $professional = $sub->get_result()->fetch_assoc();
    }
}

$companyOptions = $conn->prepare("
    SELECT company_id, company_name, status
    FROM companies
    WHERE status = 'active' OR company_id = ?
    ORDER BY company_name
");
$currentCompanyId = (int) ($profile['company_id'] ?? 0);
$companyOptions->bind_param("i", $currentCompanyId);
$companyOptions->execute();
$companyOptions = $companyOptions->get_result();

$data = [
    'email' => $profile['email'] ?? '',
    'name' => $profile['name'] ?? '',
    'phone' => $profile['phone'] ?? '',
    'skills' => $profile['skills'] ?? '',
    'location' => $profile['location'] ?? '',
    'college_university' => $student['college_university'] ?? '',
    'course' => $student['course'] ?? '',
    'branch' => $student['branch'] ?? '',
    'current_year_semester' => $student['current_year_semester'] ?? '',
    'graduation_year' => $student['graduation_year'] ?? '',
    'highest_qualification' => $professional['highest_qualification'] ?? '',
    'current_previous_company' => $professional['current_previous_company'] ?? '',
    'experience_years' => $professional['experience_years'] ?? '',
    'expected_salary' => $professional['expected_salary'] ?? '',
    'recruiter_name' => $profile['recruiter_name'] ?? '',
    'designation' => $profile['designation'] ?? '',
    'company_id' => $profile['company_id'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost();
    foreach ($data as $key => $unused) {
        if (isset($_POST[$key]) && is_string($_POST[$key])) {
            $data[$key] = trim($_POST[$key]);
        }
    }
    $errors = validateManagementData($type, $data);
    if ($errors) {
        $error = implode(' ', $errors);
    } elseif (managementEmailExists($conn, $data['email'], $editing ? (int) $userId : null)) {
        $error = "That email address is already associated with an account.";
    } else {
        $conn->begin_transaction();
        try {
            if (!$editing) {
                $created = createManagedAccount($conn, $type, $data);
                $conn->commit();
                $mailSent = sendAccountSetupEmail($created['email'], $created['setup_token']);
                $_SESSION['management_flash'] = $mailSent
                    ? 'Account created. Password setup instructions were sent.'
                    : 'Account created as pending setup, but email delivery failed. Use the resend setup action in the directory.';
                header("Location: account-form.php?type=" . rawurlencode($type) . "&created=1", true, 303);
                exit();
            }

            $email = managementEmail($data['email']);
            $emailChanged = managementEmail($profile['email']) !== $email;
            $updateUser = $conn->prepare("UPDATE users SET email = ? WHERE user_id = ? AND role = ?");
            $role = $type === 'employer' ? 'employer' : 'jobseeker';
            $updateUser->bind_param("sis", $email, $userId, $role);
            $updateUser->execute();
            $setupToken = null;
            if ($emailChanged && $profile['account_status'] === 'pending_setup') {
                $setupToken = createAccountSetupToken($conn, (int) $userId);
            }

            if ($type === 'employer') {
                $companyId = (int) $data['company_id'];
                $companyStmt = $conn->prepare("
                    SELECT company_name, industry, location, description, status
                    FROM companies
                    WHERE company_id = ? AND (status = 'active' OR company_id = ?)
                    LIMIT 1
                ");
                $currentCompanyId = (int) ($profile['company_id'] ?? 0);
                $companyStmt->bind_param("ii", $companyId, $currentCompanyId);
                $companyStmt->execute();
                $company = $companyStmt->get_result()->fetch_assoc();
                if (!$company || ($company['status'] !== 'active' && $companyId !== $currentCompanyId)) {
                    throw new RuntimeException("Select an active company.");
                }
                $update = $conn->prepare("
                    UPDATE employers
                    SET company_name = ?, industry = ?, location = ?, about = ?,
                        recruiter_name = ?, phone = ?, designation = ?, company_id = ?
                    WHERE user_id = ?
                ");
                $update->bind_param(
                    "sssssssii",
                    $company['company_name'],
                    $company['industry'],
                    $company['location'],
                    $company['description'],
                    $data['recruiter_name'],
                    $data['phone'],
                    $data['designation'],
                    $companyId,
                    $userId
                );
                $update->execute();
                writeAdminAudit($conn, 'update', 'employer', (int) $userId, 'Admin updated recruiter profile');
            } else {
                $jsId = (int) $profile['js_id'];
                $name = $data['name'];
                $phone = $data['phone'];
                $skills = $data['skills'];
                $location = $data['location'];
                $profileType = $type;
                $education = legacyEducationSummary($type === 'student'
                    ? $data['course'] . ' - ' . $data['branch']
                    : $data['highest_qualification']);
                $update = $conn->prepare("
                    UPDATE job_seekers
                    SET profile_type = ?, name = ?, phone = ?, skills = ?, location = ?, education = ?
                    WHERE js_id = ?
                ");
                $update->bind_param("ssssssi", $profileType, $name, $phone, $skills, $location, $education, $jsId);
                $update->execute();

                if ($type === 'student') {
                    $college = $data['college_university'];
                    $course = $data['course'];
                    $branch = $data['branch'];
                    $currentYear = $data['current_year_semester'];
                    $graduationYear = (int) $data['graduation_year'];
                    $subtype = $conn->prepare("
                        INSERT INTO student_profiles
                            (js_id, college_university, course, branch, current_year_semester, graduation_year)
                        VALUES (?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE college_university = VALUES(college_university),
                            course = VALUES(course), branch = VALUES(branch),
                            current_year_semester = VALUES(current_year_semester),
                            graduation_year = VALUES(graduation_year)
                    ");
                    $subtype->bind_param("issssi", $jsId, $college, $course, $branch, $currentYear, $graduationYear);
                } else {
                    $qualification = $data['highest_qualification'];
                    $company = $data['current_previous_company'];
                    $experience = (float) $data['experience_years'];
                    $salary = trim((string) $data['expected_salary']) === '' ? null : (float) $data['expected_salary'];
                    $subtype = $conn->prepare("
                        INSERT INTO professional_profiles
                            (js_id, highest_qualification, current_previous_company, experience_years, expected_salary)
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE highest_qualification = VALUES(highest_qualification),
                            current_previous_company = VALUES(current_previous_company),
                            experience_years = VALUES(experience_years), expected_salary = VALUES(expected_salary)
                    ");
                    $subtype->bind_param("issdd", $jsId, $qualification, $company, $experience, $salary);
                }
                $subtype->execute();
                writeAdminAudit($conn, 'update', $type, (int) $userId, 'Admin updated job-seeker profile');
            }

            $conn->commit();
            if ($setupToken !== null) {
                $mailSent = sendAccountSetupEmail($email, $setupToken);
                $_SESSION['management_flash'] = $mailSent
                    ? 'Account details updated. A new password setup link was sent to the updated email.'
                    : 'Account details updated, but email delivery failed. Use the resend setup action in the directory.';
            } else {
                $_SESSION['management_flash'] = 'Account details updated.';
            }
            header("Location: account-form.php?type=" . rawurlencode($type) . "&id=" . (int) $userId . "&updated=1", true, 303);
            exit();
        } catch (Throwable $exception) {
            $conn->rollback();
            error_log("Admin account management failed: " . $exception->getMessage());
            $error = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : "The account could not be saved. Check the email and account details.";
        }
    }
}

if (isset($_GET['created']) || isset($_GET['updated'])) {
    $notice = (string) ($_SESSION['management_flash'] ?? '');
    unset($_SESSION['management_flash']);
}

include __DIR__ . "/../includes/header.php";
?>
<main class="page-section">
    <div class="page-heading">
        <p class="eyebrow">Admin account management</p>
        <h1><?php echo $editing ? 'Edit' : 'Create'; ?> <?php echo e(ucfirst($type)); ?> account</h1>
    </div>
    <?php if ($error !== ''): ?><p class="form-error" role="alert"><?php echo e($error); ?></p><?php endif; ?>
    <?php if ($notice !== ''): ?><p class="form-success" role="status"><?php echo e($notice); ?></p><?php endif; ?>
    <?php if (!$editing): ?><p>New accounts remain pending until the recipient sets a password through the emailed one-time setup link.</p><?php endif; ?>
    <form method="POST" class="job-detail-card">
        <?php echo csrfInput(); ?>
        <div class="form-grid">
            <label>Email
                <input type="email" name="email" maxlength="100" required value="<?php echo e($data['email']); ?>">
            </label>
            <?php if ($type === 'student' || $type === 'professional'): ?>
                <label>Full name
                    <input type="text" name="name" maxlength="100" required value="<?php echo e($data['name']); ?>">
                </label>
                <label>Phone
                    <input type="tel" name="phone" maxlength="20" required value="<?php echo e($data['phone']); ?>">
                </label>
                <label>Location
                    <input type="text" name="location" maxlength="100" required value="<?php echo e($data['location']); ?>">
                </label>
                <label>Skills
                    <textarea name="skills" maxlength="5000" required><?php echo e($data['skills']); ?></textarea>
                </label>
                <?php if ($type === 'student'): ?>
                    <label>College / University
                        <input type="text" name="college_university" maxlength="180" required value="<?php echo e($data['college_university']); ?>">
                    </label>
                    <label>Course
                        <input type="text" name="course" maxlength="150" required value="<?php echo e($data['course']); ?>">
                    </label>
                    <label>Branch
                        <input type="text" name="branch" maxlength="150" required value="<?php echo e($data['branch']); ?>">
                    </label>
                    <label>Current year / semester
                        <input type="text" name="current_year_semester" maxlength="50" required value="<?php echo e($data['current_year_semester']); ?>">
                    </label>
                    <label>Graduation year
                        <input type="number" name="graduation_year" min="1950" max="<?php echo (int) date('Y') + 10; ?>" required value="<?php echo e($data['graduation_year']); ?>">
                    </label>
                <?php else: ?>
                    <label>Highest qualification
                        <input type="text" name="highest_qualification" maxlength="150" required value="<?php echo e($data['highest_qualification']); ?>">
                    </label>
                    <label>Current / previous company
                        <input type="text" name="current_previous_company" maxlength="180" value="<?php echo e($data['current_previous_company']); ?>">
                    </label>
                    <label>Experience in years
                        <input type="number" name="experience_years" min="0" max="999.99" step="0.01" required value="<?php echo e($data['experience_years']); ?>">
                    </label>
                    <label>Expected salary (optional)
                        <input type="number" name="expected_salary" min="0" max="9999999999.99" step="0.01" value="<?php echo e($data['expected_salary']); ?>">
                    </label>
                <?php endif; ?>
            <?php else: ?>
                <label>Recruiter name
                    <input type="text" name="recruiter_name" maxlength="100" required value="<?php echo e($data['recruiter_name']); ?>">
                </label>
                <label>Phone
                    <input type="tel" name="phone" maxlength="20" required value="<?php echo e($data['phone']); ?>">
                </label>
                <label>Designation
                    <input type="text" name="designation" maxlength="100" required value="<?php echo e($data['designation']); ?>">
                </label>
                <label>Company
                    <select name="company_id" required>
                        <option value="">Select an active company</option>
                        <?php while ($companyOption = $companyOptions->fetch_assoc()): ?>
                            <option value="<?php echo (int) $companyOption['company_id']; ?>" <?php echo (string) $data['company_id'] === (string) $companyOption['company_id'] ? 'selected' : ''; ?>><?php echo e($companyOption['company_name'] . ($companyOption['status'] === 'inactive' ? ' (inactive)' : '')); ?></option>
                        <?php endwhile; ?>
                    </select>
                    <a href="company-form.php">Add a company record</a>
                </label>
            <?php endif; ?>
        </div>
        <button type="submit"><?php echo $editing ? 'Save changes' : 'Create account'; ?></button>
        <a href="<?php echo $type === 'professional' ? 'manage-employees.php' : 'manage-' . ($type === 'employer' ? 'employers' : 'students') . '.php'; ?>">Cancel</a>
    </form>
</main>
<?php include __DIR__ . "/../includes/footer.php"; ?>
