<?php
require_once __DIR__ . "/../includes/session.php";
requireEmployer();
requireCsrfPost();

$postedApplicationId = $_POST['application_id'] ?? "";
if (!is_string($postedApplicationId) || !ctype_digit($postedApplicationId) || (int) $postedApplicationId < 1) {
    http_response_code(400);
    exit("Invalid application.");
}

$applicationId = (int) $postedApplicationId;
$status = is_string($_POST['status'] ?? null) ? $_POST['status'] : "";
$allowedStatuses = ["Pending", "Shortlisted", "Rejected", "Hired"];
if (!in_array($status, $allowedStatuses, true)) {
    http_response_code(400);
    exit("Invalid application status.");
}

$userId = (int) $_SESSION['user_id'];
$owned = $conn->prepare("
    SELECT applications.application_id
    FROM applications
    JOIN jobs ON jobs.job_id = applications.job_id
    JOIN employers ON employers.emp_id = jobs.emp_id
    WHERE applications.application_id = ? AND employers.user_id = ?
");
$owned->bind_param("ii", $applicationId, $userId);
$owned->execute();
if (!$owned->get_result()->fetch_assoc()) {
    http_response_code(404);
    exit("Application not found.");
}

$stmt = $conn->prepare("
    UPDATE applications
    JOIN jobs ON jobs.job_id = applications.job_id
    JOIN employers ON employers.emp_id = jobs.emp_id
    SET applications.status = ?
    WHERE applications.application_id = ? AND employers.user_id = ?
");
$stmt->bind_param("sii", $status, $applicationId, $userId);

if ($stmt->execute()) {
    header("Location: view-applicants.php", true, 303);
    exit();
}
error_log("Application status update failed: " . $stmt->error);
http_response_code(500);
exit("The application status could not be updated.");
?>