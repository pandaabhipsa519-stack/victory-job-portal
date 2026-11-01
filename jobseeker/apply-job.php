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

$job_id = (int) $postedJobId;

$conn->begin_transaction();
try {
    $job = $conn->prepare("
        SELECT jobs.job_id
        FROM jobs
        JOIN employers ON employers.emp_id = jobs.emp_id
        JOIN users ON users.user_id = employers.user_id
        LEFT JOIN companies ON companies.company_id = employers.company_id
        WHERE jobs.job_id = ?
          AND users.account_status = 'active'
          AND (employers.company_id IS NULL OR companies.status = 'active')
        FOR UPDATE
    ");
    $job->bind_param("i", $job_id);
    $job->execute();
    if (!$job->get_result()->fetch_assoc()) {
        $conn->rollback();
        http_response_code(404);
        exit("Job not found.");
    }

    $check = $conn->prepare("SELECT application_id FROM applications WHERE user_id = ? AND job_id = ? LIMIT 1");
    $check->bind_param("ii", $user_id, $job_id);
    $check->execute();
    if ($check->get_result()->fetch_assoc()) {
        $conn->rollback();
        header("Location: my-applications.php?result=already", true, 303);
        exit();
    }

    $insert = $conn->prepare("INSERT INTO applications (user_id, job_id, status) VALUES (?, ?, 'Pending')");
    $insert->bind_param("ii", $user_id, $job_id);
    if (!$insert->execute()) {
        throw new RuntimeException("Application insert failed: " . $insert->error);
    }

    $notification = $conn->prepare("INSERT INTO notifications (user_id, job_id, message) VALUES (?, ?, 'Application submitted successfully')");
    $notification->bind_param("ii", $user_id, $job_id);
    if (!$notification->execute()) {
        throw new RuntimeException("Application notification insert failed: " . $notification->error);
    }

    $conn->commit();
    header("Location: my-applications.php?result=applied", true, 303);
    exit();
} catch (Throwable $exception) {
    $conn->rollback();
    error_log("Job application failed: " . $exception->getMessage());
    http_response_code(500);
    exit("The application could not be submitted. Please try again.");
}
?>