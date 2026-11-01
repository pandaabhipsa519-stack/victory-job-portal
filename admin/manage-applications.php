<?php
require_once __DIR__ . "/../includes/session.php";
requireAdmin();

$applications = $conn->query("
    SELECT applications.application_id, applications.status, applications.applied_date,
           jobs.title, employers.company_name, job_seekers.name, users.email
    FROM applications
    JOIN jobs ON jobs.job_id = applications.job_id
    JOIN employers ON employers.emp_id = jobs.emp_id
    JOIN users ON users.user_id = applications.user_id
    LEFT JOIN job_seekers ON job_seekers.user_id = applications.user_id
    ORDER BY applications.applied_date DESC
");
include __DIR__ . "/../includes/header.php";
?>

<h1 class="dashboard-title">Manage Applications</h1>
<table class="app-table">
    <thead><tr><th>Candidate</th><th>Email</th><th>Job</th><th>Company</th><th>Status</th><th>Applied</th></tr></thead>
    <tbody>
    <?php while ($application = $applications->fetch_assoc()): ?>
        <tr>
            <td><?php echo e($application['name']); ?></td>
            <td><?php echo e($application['email']); ?></td>
            <td><?php echo e($application['title']); ?></td>
            <td><?php echo e($application['company_name']); ?></td>
            <td><?php echo e($application['status']); ?></td>
            <td><?php echo e($application['applied_date']); ?></td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

<?php include __DIR__ . "/../includes/footer.php"; ?>
