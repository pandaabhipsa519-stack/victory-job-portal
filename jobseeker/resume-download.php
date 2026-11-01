<?php
require_once __DIR__ . "/../includes/session.php";
requireLogin();

$applicationId = filter_var(
    $_GET['application_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
if ($applicationId === false || $applicationId === null) {
    http_response_code(400);
    exit("Invalid application.");
}

$stmt = $conn->prepare("
    SELECT applications.user_id, jobs.emp_id, job_seekers.resume
    FROM applications
    JOIN jobs ON jobs.job_id = applications.job_id
    JOIN job_seekers ON job_seekers.user_id = applications.user_id
    WHERE applications.application_id = ?
");
$stmt->bind_param("i", $applicationId);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();
if (!$application || empty($application['resume'])) {
    http_response_code(404);
    exit("Resume not found.");
}

$authorized = $_SESSION['role'] === 'admin';
if ($_SESSION['role'] === 'jobseeker') {
    $authorized = (int) $application['user_id'] === (int) $_SESSION['user_id'];
} elseif ($_SESSION['role'] === 'employer') {
    requireEmployer();
    $owner = $conn->prepare("SELECT emp_id FROM employers WHERE user_id = ? LIMIT 1");
    $userId = (int) $_SESSION['user_id'];
    $owner->bind_param("i", $userId);
    $owner->execute();
    $employer = $owner->get_result()->fetch_assoc();
    $authorized = $employer && (int) $employer['emp_id'] === (int) $application['emp_id'];
}

if (!$authorized) {
    http_response_code(403);
    exit("Access denied.");
}

$filename = basename(str_replace('\\', '/', $application['resume']));
$resumeDirectory = realpath(__DIR__ . "/../uploads/resumes");
$path = $resumeDirectory ? realpath($resumeDirectory . DIRECTORY_SEPARATOR . $filename) : false;
if (!$path || strpos($path, $resumeDirectory . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
    http_response_code(404);
    exit("Resume file not found.");
}

$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$contentTypes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
header('Content-Type: ' . ($contentTypes[$extension] ?? 'application/octet-stream'));
header('Content-Disposition: attachment; filename="resume-' . (int) $applicationId . '.' . (isset($contentTypes[$extension]) ? $extension : 'bin') . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
