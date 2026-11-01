<?php
require_once __DIR__ . "/../includes/session.php";

requireJobseeker();
requireCsrfPost();

$user_id = (int) $_SESSION['user_id'];

$postedJobId = $_POST['job_id'] ?? "";
if (!is_string($postedJobId) || !ctype_digit($postedJobId) || (int) $postedJobId < 1) {
    http_response_code(400);
    exit("Invalid job.");
}

$result = saveJobForUser($conn, $user_id, (int) $postedJobId);
if ($result === 'not_found') {
    http_response_code(404);
    exit("Job not found.");
}

header("Location: saved-jobs.php?result=" . rawurlencode($result), true, 303);
exit();