<?php
require_once "../includes/session.php";
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost();
    $jobId = filter_var($_POST['job_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($jobId === false || $jobId === null) {
        http_response_code(400);
        exit("Invalid job.");
    }

    $delete = $conn->prepare("DELETE FROM jobs WHERE job_id = ?");
    $delete->bind_param("i", $jobId);
    $delete->execute();
    if ($delete->affected_rows !== 1) {
        http_response_code(404);
        exit("Job not found.");
    }
    header("Location: manage-jobs.php", true, 303);
    exit();
}

/* FETCH JOBS */
$jobs = $conn->query("
SELECT jobs.*, employers.company_name
FROM jobs
JOIN employers ON jobs.emp_id = employers.emp_id
ORDER BY jobs.job_id DESC
");
include "../includes/header.php";

?>

<h1 class="dashboard-title">Manage Jobs 💼</h1>

<table class="app-table">

<tr>
<th>ID</th>
<th>Title</th>
<th>Company</th>
<th>Location</th>
<th>Type</th>
<th>Action</th>
</tr>

<?php while($job = $jobs->fetch_assoc()): ?>

<tr>

<td><?php echo (int) $job['job_id']; ?></td>

<td><?php echo e($job['title']); ?></td>

<td><?php echo e($job['company_name']); ?></td>

<td><?php echo e($job['location']); ?></td>

<td><?php echo e($job['job_type']); ?></td>

<td>

<form method="POST" action="manage-jobs.php" onsubmit="return confirm('Delete this job?');">
    <?php echo csrfInput(); ?>
    <input type="hidden" name="job_id" value="<?php echo (int) $job['job_id']; ?>">
    <button type="submit">Delete</button>
</form>

</td>

</tr>

<?php endwhile; ?>

</table>

<?php include "../includes/footer.php"; ?>