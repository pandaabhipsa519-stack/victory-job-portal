<?php
require_once __DIR__ . "/../includes/session.php";
requireAdmin();

$companyId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($companyId === false || $companyId === null) {
    http_response_code(400);
    exit("Invalid company.");
}
$stmt = $conn->prepare("
    SELECT c.*, COUNT(e.emp_id) AS recruiter_count
    FROM companies c
    LEFT JOIN employers e ON e.company_id = c.company_id
    WHERE c.company_id = ?
    GROUP BY c.company_id
    LIMIT 1
");
$stmt->bind_param("i", $companyId);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();
if (!$company) {
    http_response_code(404);
    exit("Company not found.");
}

include __DIR__ . "/../includes/header.php";
?>
<main class="page-section">
    <div class="page-heading">
        <p class="eyebrow">Admin organization details</p>
        <h1><?php echo e($company['company_name']); ?></h1>
    </div>
    <section class="job-detail-card">
        <p><strong>Status:</strong> <?php echo e($company['status']); ?></p>
        <p><strong>Industry:</strong> <?php echo e($company['industry']); ?></p>
        <p><strong>Website:</strong> <?php echo e($company['website']); ?></p>
        <p><strong>Official email:</strong> <?php echo e($company['official_email']); ?></p>
        <p><strong>Phone:</strong> <?php echo e($company['phone']); ?></p>
        <p><strong>Location:</strong> <?php echo e($company['location']); ?></p>
        <p><strong>Description:</strong><br><?php echo nl2br(e($company['description'])); ?></p>
        <p><strong>Linked recruiter profiles:</strong> <?php echo (int) $company['recruiter_count']; ?></p>
        <?php if (!empty($company['logo'])): ?>
            <p><strong>Logo:</strong> <?php echo e(basename($company['logo'])); ?></p>
        <?php endif; ?>
        <a href="company-form.php?id=<?php echo (int) $companyId; ?>">Edit company</a>
        <a href="manage-companies.php">Back to companies</a>
    </section>
</main>
<?php include __DIR__ . "/../includes/footer.php"; ?>
