<?php
require_once __DIR__ . "/../includes/session.php";
requireEmployer();
requireCsrfPost();

$postedJobId = $_POST['id'] ?? "";
if (!is_string($postedJobId) || !ctype_digit($postedJobId) || (int) $postedJobId < 1) {
    http_response_code(400);
    exit("Invalid job.");
}

$job_id = (int) $postedJobId;
$userId = (int) $_SESSION['user_id'];
$owner = $conn->prepare("SELECT emp_id FROM employers WHERE user_id = ? LIMIT 1");
$owner->bind_param("i", $userId);
$owner->execute();
$employer = $owner->get_result()->fetch_assoc();
if (!$employer) {
    http_response_code(403);
    exit("Employer profile not found.");
}
$emp_id = (int) $employer['emp_id'];

/* SECURITY CHECK */
$stmt = $conn->prepare("
DELETE FROM jobs
WHERE job_id=? AND emp_id=?
");

$stmt->bind_param("ii",$job_id,$emp_id);

if ($stmt->execute() && $stmt->affected_rows === 1) {
    header("Location: manage-jobs.php", true, 303);
    exit();
}
http_response_code(404);
exit("Job not found.");
?>