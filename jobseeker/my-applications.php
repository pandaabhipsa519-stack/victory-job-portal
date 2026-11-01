<?php
require_once __DIR__ . "/../includes/session.php";
requireJobseeker();

$user = (int) $_SESSION['user_id'];
$stmt = $conn->prepare("
SELECT jobs.title,applications.status,applications.applied_date
FROM applications
JOIN jobs ON applications.job_id=jobs.job_id
WHERE applications.user_id=?
ORDER BY applications.applied_date DESC
");
$stmt->bind_param("i", $user);
$stmt->execute();
$app = $stmt->get_result();
include __DIR__ . "/../includes/header.php";
include __DIR__ . "/layout.php";
?>

<h2>My Applications</h2>

<table class="app-table">
<tr><th>Job</th><th>Status</th><th>Date</th></tr>
<?php while ($row = $app->fetch_assoc()): ?>
<tr>
<td><?php echo e($row['title']); ?></td>
<td><?php echo e($row['status']); ?></td>
<td><?php echo e($row['applied_date']); ?></td>
</tr>
<?php endwhile; ?>
</table>

</div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>