<?php
require_once __DIR__ . "/includes/session.php";
requireJobseeker();

requireCsrfPost();

$postedJobId = $_POST['job_id'] ?? "";
if (!is_string($postedJobId) || !ctype_digit($postedJobId) || (int) $postedJobId < 1) {
    http_response_code(400);
    exit("invalid_job");
}

$result = saveJobForUser($conn, (int) $_SESSION['user_id'], (int) $postedJobId);
if ($result === 'not_found') {
    http_response_code(404);
    exit("not_found");
}

echo $result;
?>