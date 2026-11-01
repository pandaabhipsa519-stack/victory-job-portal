<?php
include "includes/header.php";

$requestedJobId = $_GET['id'] ?? "";
if (!is_string($requestedJobId) || !ctype_digit($requestedJobId) || (int) $requestedJobId < 1) {
    http_response_code(404);
    echo "<div class=\"container\"><h1>Job not found</h1><p>This job listing does not exist.</p><a href=\"/victory/jobs.php\">Browse jobs</a></div>";
    include "includes/footer.php";
    exit();
}

$job_id = (int) $requestedJobId;

$stmt = $conn->prepare("
    SELECT jobs.*, COALESCE(companies.company_name, employers.company_name) AS company_name,
           COALESCE(companies.industry, employers.industry) AS industry
    FROM jobs
    JOIN employers ON jobs.emp_id = employers.emp_id
    JOIN users ON users.user_id = employers.user_id
    LEFT JOIN companies ON companies.company_id = employers.company_id
    WHERE jobs.job_id = ?
      AND users.account_status = 'active'
      AND (employers.company_id IS NULL OR companies.status = 'active')
");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
if (!$job) {
    http_response_code(404);
    echo "<div class=\"container\"><h1>Job not found</h1><p>This job listing does not exist.</p><a href=\"/victory/jobs.php\">Browse jobs</a></div>";
    include "includes/footer.php";
    exit();
}
?>

<main class="page-section">
<a class="back-link" href="/victory/jobs.php"><span aria-hidden="true">←</span> Back to jobs</a>
<article class="job-detail-card">

<h1><?php echo e($job['title']); ?></h1>

<p class="company">
<i class="fa fa-building" aria-hidden="true"></i> <b><?php echo e($job['company_name']); ?></b>
</p>

<div class="job-meta">

<span><i class="fa fa-location-dot" aria-hidden="true"></i> <?php echo e($job['location']); ?></span>

<span><i class="fa fa-briefcase" aria-hidden="true"></i> <?php echo e($job['job_type']); ?></span>

<?php if (!empty($job['salary'])): ?>
<span><i class="fa fa-coins" aria-hidden="true"></i> <?php echo e($job['salary']); ?></span>
<?php endif; ?>

<span><i class="fa fa-calendar" aria-hidden="true"></i> Posted: <?php echo e($job['posted_date']); ?></span>

</div>

<hr>

<h3>Job Description</h3>

<p class="job-description">
<?php echo nl2br(e($job['description'])); ?>
</p>

<br>

<div class="job-actions">

<?php if(isset($_SESSION['role']) && $_SESSION['role']=="jobseeker"): ?>

<form method="POST" action="jobseeker/apply-job.php">
<?php echo csrfInput(); ?>
<input type="hidden" name="job_id" value="<?php echo (int) $job['job_id']; ?>">
<button class="apply-btn" type="submit">Apply Now</button>
</form>

<form method="POST" action="jobseeker/save-job.php">
<?php echo csrfInput(); ?>
<input type="hidden" name="job_id" value="<?php echo (int) $job['job_id']; ?>">
<button class="save-btn" type="submit">⭐ Save Job</button>
</form>

<?php else: ?>

<a class="login-btn"
href="auth/login.php">
Login to Apply
</a>

<?php endif; ?>

</div>
</article>
</main>

<?php include "includes/footer.php"; ?>