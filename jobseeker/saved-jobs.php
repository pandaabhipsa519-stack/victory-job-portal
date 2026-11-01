<?php
require_once __DIR__ . "/../includes/session.php";
requireJobseeker();

$user_id = (int) $_SESSION['user_id'];
$stmt = $conn->prepare("
SELECT jobs.*, employers.company_name
FROM saved_jobs
JOIN jobs ON saved_jobs.job_id = jobs.job_id
JOIN employers ON jobs.emp_id = employers.emp_id
JOIN users ON users.user_id = employers.user_id
LEFT JOIN companies ON companies.company_id = employers.company_id
WHERE saved_jobs.user_id = ?
AND users.account_status = 'active'
AND (employers.company_id IS NULL OR companies.status = 'active')
ORDER BY saved_jobs.saved_date DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$saved = $stmt->get_result();
include __DIR__ . "/../includes/header.php";
include __DIR__ . "/layout.php";
?>

<h2>Saved Jobs ⭐</h2>

<div class="job-grid">

<?php while($job = $saved->fetch_assoc()): ?>

<div class="job-card-modern">

<h3><?php echo e($job['title']); ?></h3>

<p><b><?php echo e($job['company_name']); ?></b></p>

<p><?php echo e($job['location']); ?></p>

<a href="../job-details.php?id=<?php echo (int) $job['job_id']; ?>">
View Job
</a>

</div>

<?php endwhile; ?>

</div>

</div>
</div>

<?php include "../includes/footer.php"; ?>