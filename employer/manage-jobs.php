<?php
require_once __DIR__ . "/../includes/session.php";
requireEmployer();

$userId = (int) $_SESSION['user_id'];
$employerStmt = $conn->prepare("SELECT emp_id FROM employers WHERE user_id = ? LIMIT 1");
$employerStmt->bind_param("i", $userId);
$employerStmt->execute();
$employer = $employerStmt->get_result()->fetch_assoc();

if (!$employer) {
    http_response_code(403);
    exit("Employer profile not found.");
}
$employerId = (int) $employer['emp_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost();
    $jobId = filter_var($_POST['job_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($jobId === false || $jobId === null) {
        http_response_code(400);
        exit("Invalid job.");
    }

    $delete = $conn->prepare("DELETE FROM jobs WHERE job_id = ? AND emp_id = ?");
    $delete->bind_param("ii", $jobId, $employerId);
    $delete->execute();
    if ($delete->affected_rows !== 1) {
        http_response_code(404);
        exit("Job not found.");
    }
    header("Location: manage-jobs.php", true, 303);
    exit();
}

$jobsStmt = $conn->prepare("SELECT job_id, title, location, job_type, posted_date FROM jobs WHERE emp_id = ? ORDER BY posted_date DESC");
$jobsStmt->bind_param("i", $employerId);
$jobsStmt->execute();
$jobs = $jobsStmt->get_result();

include __DIR__ . "/../includes/header.php";
?>

<h1 class="dashboard-title">Manage Jobs</h1>
<p><a href="post-job.php">Post a new job</a></p>

<?php if ($jobs->num_rows > 0): ?>
    <?php while ($job = $jobs->fetch_assoc()): ?>
        <div class="job-card-modern">
            <h2><?php echo e($job['title']); ?></h2>
            <p><?php echo e($job['location']); ?> · <?php echo e($job['job_type']); ?></p>
            <p>Posted <?php echo e($job['posted_date']); ?></p>
            <a href="edit-job.php?id=<?php echo (int) $job['job_id']; ?>">Edit</a>
            <form method="POST" action="manage-jobs.php" onsubmit="return confirm('Delete this job?');">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="job_id" value="<?php echo (int) $job['job_id']; ?>">
                <button type="submit">Delete</button>
            </form>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <p>You have not posted any jobs yet.</p>
<?php endif; ?>

<?php include __DIR__ . "/../includes/footer.php"; ?>
