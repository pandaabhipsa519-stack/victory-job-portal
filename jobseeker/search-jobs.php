<?php
require_once __DIR__ . "/../includes/session.php";
requireJobseeker();

$jobs = $conn->query("
    SELECT jobs.job_id, jobs.title, jobs.location, employers.company_name
    FROM jobs
    JOIN employers ON jobs.emp_id = employers.emp_id
    JOIN users ON users.user_id = employers.user_id
    LEFT JOIN companies ON companies.company_id = employers.company_id
    WHERE users.account_status = 'active'
      AND (employers.company_id IS NULL OR companies.status = 'active')
    ORDER BY jobs.posted_date DESC
    LIMIT 100
");
include __DIR__ . "/../includes/header.php";
include __DIR__ . "/layout.php";
?>

<h2>Search Jobs</h2>

<input type="text" id="jobSearch" placeholder="Search job title...">

<div class="job-grid">

<?php while ($job = $jobs->fetch_assoc()): ?>

<div class="job-card-modern job-card">

<h3><?php echo e($job['title']); ?></h3>

<p><?php echo e($job['company_name']); ?></p>

<p><?php echo e($job['location']); ?></p>

<a href="../job-details.php?id=<?php echo (int) $job['job_id']; ?>">
View Details
</a>

</div>

<?php endwhile; ?>

</div>

</div></div>

<?php include "../includes/footer.php"; ?>