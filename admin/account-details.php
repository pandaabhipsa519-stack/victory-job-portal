<?php
require_once __DIR__ . "/../includes/session.php";
requireAdmin();

$type = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
$userId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!in_array($type, ['student', 'professional', 'employer'], true) || $userId === false || $userId === null) {
    http_response_code(400);
    exit("Invalid account.");
}

if ($type === 'employer') {
    $stmt = $conn->prepare("
        SELECT u.email,u.account_status,e.recruiter_name,e.phone,e.designation,
               e.company_name,e.industry,e.location,c.website,c.official_email AS company_email,c.status AS company_status
        FROM users u JOIN employers e ON e.user_id=u.user_id
        LEFT JOIN companies c ON c.company_id=e.company_id
        WHERE u.user_id=? AND u.role='employer' LIMIT 1
    ");
} else {
    $stmt = $conn->prepare("
        SELECT u.email,u.account_status,js.name,js.phone,js.skills,js.location,js.about,
               js.profile_type,js.education,sp.college_university,sp.course,sp.branch,
               sp.current_year_semester,sp.graduation_year,pp.highest_qualification,
               pp.current_previous_company,pp.experience_years,pp.expected_salary
        FROM users u JOIN job_seekers js ON js.user_id=u.user_id
        LEFT JOIN student_profiles sp ON sp.js_id=js.js_id
        LEFT JOIN professional_profiles pp ON pp.js_id=js.js_id
        WHERE u.user_id=? AND u.role='jobseeker' LIMIT 1
    ");
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();
if (!$account) {
    http_response_code(404);
    exit("Account not found.");
}
if ($type !== 'employer' && !in_array($account['profile_type'], ['unclassified', $type], true)) {
    http_response_code(409);
    exit("This account belongs to a different job-seeker directory.");
}

$fields = $type === 'employer'
    ? ['Recruiter' => 'recruiter_name', 'Phone' => 'phone', 'Designation' => 'designation', 'Company' => 'company_name', 'Industry' => 'industry', 'Location' => 'location', 'Website' => 'website', 'Company email' => 'company_email', 'Company status' => 'company_status']
    : ($type === 'student'
        ? ['Name' => 'name', 'Phone' => 'phone', 'Location' => 'location', 'Skills' => 'skills', 'College / University' => 'college_university', 'Course' => 'course', 'Branch' => 'branch', 'Current year / semester' => 'current_year_semester', 'Graduation year' => 'graduation_year']
        : ['Name' => 'name', 'Phone' => 'phone', 'Location' => 'location', 'Skills' => 'skills', 'Highest qualification' => 'highest_qualification', 'Current / previous company' => 'current_previous_company', 'Experience (years)' => 'experience_years', 'Expected salary' => 'expected_salary']);

include __DIR__ . "/../includes/header.php";
?>
<main class="page-section">
    <div class="page-heading">
        <p class="eyebrow">Admin account details</p>
        <h1><?php echo e(ucfirst($type)); ?> account</h1>
    </div>
    <section class="job-detail-card">
        <p><strong>Email:</strong> <?php echo e($account['email']); ?></p>
        <p><strong>Account status:</strong> <?php echo e(str_replace('_', ' ', $account['account_status'])); ?></p>
        <?php if ($type !== 'employer' && $account['profile_type'] !== $type): ?>
            <p>This job-seeker profile is currently classified as <?php echo e($account['profile_type']); ?>. Its type-specific fields may be incomplete.</p>
        <?php endif; ?>
        <?php foreach ($fields as $label => $key): ?>
            <p><strong><?php echo e($label); ?>:</strong> <?php echo e($account[$key] ?? ''); ?></p>
        <?php endforeach; ?>
        <a href="account-form.php?type=<?php echo e($type); ?>&amp;id=<?php echo (int) $userId; ?>">Edit account</a>
        <a href="<?php echo $type === 'professional' ? 'manage-employees.php' : 'manage-' . ($type === 'employer' ? 'employers' : 'students') . '.php'; ?>">Back to directory</a>
    </section>
</main>
<?php include __DIR__ . "/../includes/footer.php"; ?>
