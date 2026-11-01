<?php
require_once "includes/config.php";
require_once "includes/session.php";
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
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<?php include "includes/header.php"; ?>
<h2>Saved Jobs</h2>

<?php while($job = $result->fetch_assoc()): ?>

<div class="job-card-modern">
    <h3><?php echo e($job['title']); ?></h3>
    <p><?php echo e($job['company_name']); ?></p>
    <p><?php echo e($job['location']); ?></p>
</div>

<?php endwhile; ?>

<?php include "includes/footer.php"; ?>