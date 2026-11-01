<?php
require_once "../includes/session.php";
requireEmployer();

include "../includes/header.php";
require_once "../includes/config.php";

$user_id = (int) $_SESSION['user_id'];

$get = $conn->prepare("SELECT emp_id FROM employers WHERE user_id=?");
$get->bind_param("i", $user_id);
$get->execute();
$result = $get->get_result();
$emp = $result->fetch_assoc();

if (!$emp) {
    http_response_code(403);
    exit("Employer profile not found.");
}
$emp_id = (int) $emp['emp_id'];

/* Fetch applicants */
$applicantStmt = $conn->prepare("
SELECT
applications.application_id,
applications.status,
applications.applied_date,

jobs.title,

job_seekers.name,
job_seekers.skills,
job_seekers.resume,

users.email

FROM applications

JOIN jobs ON applications.job_id = jobs.job_id
JOIN job_seekers ON applications.user_id = job_seekers.user_id
JOIN users ON users.user_id = job_seekers.user_id

WHERE jobs.emp_id = ?
ORDER BY applications.application_id DESC
");
$applicantStmt->bind_param("i", $emp_id);
$applicantStmt->execute();
$applicants = $applicantStmt->get_result();
?>

<h1 class="dashboard-title">Applicants 👥</h1>

<table class="app-table">

<tr>
<th>Name</th>
<th>Email</th>
<th>Job</th>
<th>Skills</th>
<th>Resume</th>
<th>Status</th>
<th>Date</th>
</tr>

<?php if($applicants->num_rows > 0): ?>

<?php while($row = $applicants->fetch_assoc()): ?>

<tr>

<td><?php echo e($row['name']); ?></td>

<td><?php echo e($row['email']); ?></td>

<td><?php echo e($row['title']); ?></td>

<td><?php echo e($row['skills']); ?></td>

<td>
<?php if (!empty($row['resume'])): ?>
<a href="../jobseeker/resume-download.php?application_id=<?php echo (int) $row['application_id']; ?>">Download</a>
<?php else: ?>
No resume
<?php endif; ?>
</td>

<td>

<?php echo e($row['status']); ?>
<?php foreach (["Shortlisted" => "Select", "Rejected" => "Reject", "Hired" => "Hire"] as $status => $label): ?>
<form method="POST" action="update-status.php">
    <?php echo csrfInput(); ?>
    <input type="hidden" name="application_id" value="<?php echo (int) $row['application_id']; ?>">
    <input type="hidden" name="status" value="<?php echo e($status); ?>">
    <button type="submit"><?php echo e($label); ?></button>
</form>
<?php endforeach; ?>

</td>

<td><?php echo e($row['applied_date']); ?></td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>
<td colspan="7">No applicants yet</td>
</tr>

<?php endif; ?>

</table>

<?php include "../includes/footer.php"; ?>