<?php
require_once __DIR__ . "/../includes/session.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit("Method not allowed.");
}

$keyword = $_POST['keyword'] ?? "";
if (!is_string($keyword)) {
    http_response_code(400);
    exit("Invalid search.");
}
$keyword = trim($keyword);
if (strlen($keyword) > 150) {
    http_response_code(400);
    exit("Search text is too long.");
}

$search = "%" . $keyword . "%";
$stmt = $conn->prepare("
    SELECT jobs.job_id, jobs.title, jobs.location, employers.company_name
    FROM jobs
    JOIN employers ON jobs.emp_id = employers.emp_id
    JOIN users ON users.user_id = employers.user_id
    LEFT JOIN companies ON companies.company_id = employers.company_id
    WHERE users.account_status = 'active'
      AND (employers.company_id IS NULL OR companies.status = 'active')
      AND (jobs.title LIKE ? OR jobs.location LIKE ?)
    ORDER BY jobs.posted_date DESC
    LIMIT 20
");
$stmt->bind_param("ss", $search, $search);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    exit("<p>No jobs found.</p>");
}

while ($job = $result->fetch_assoc()) {
    $jobId = (int) $job['job_id'];
    echo '<div class="job-card-modern">';
    echo '<h3>' . e($job['title']) . '</h3>';
    echo '<p><b>' . e($job['company_name']) . '</b></p>';
    echo '<p>' . e($job['location']) . '</p>';
    echo '<a href="/victory/job-details.php?id=' . $jobId . '">View Job</a>';
    echo '</div>';
}
