<?php
require_once __DIR__ . "/../includes/session.php";
requireEmployer();

/* =========================
   CHECK LOGIN
========================= */

/* =========================
   GET emp_id FROM employers TABLE
========================= */
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

$emp_id = $emp['emp_id'];

/* =========================
   POST JOB
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
requireCsrfPost();

$title = inputString($_POST['title'] ?? "", 150);
$description = inputString($_POST['description'] ?? "");
$location = inputString($_POST['location'] ?? "", 100);
$type = inputString($_POST['type'] ?? "", 20);
$salary = inputString($_POST['salary'] ?? "", 50);

if ($title === "" || strlen($title) > 150 || $description === "" || $location === ""
    || !in_array($type, ["Full-time", "Part-time", "Remote"], true)) {
    http_response_code(400);
    exit("Please provide a valid job title, description, location, and job type.");
}

$stmt = $conn->prepare("
INSERT INTO jobs (emp_id, title, description, location, job_type, salary, posted_date)
VALUES (?,?,?,?,?,?,NOW())
");

$stmt->bind_param("isssss", $emp_id, $title, $description, $location, $type, $salary);

if($stmt->execute()){
    header("Location: dashboard.php", true, 303);
    exit();
}else{
    error_log("Job creation failed: " . $stmt->error);
    http_response_code(500);
    exit("The job could not be posted.");
}

}
include __DIR__ . "/../includes/header.php";
?>

<h2>Post a New Job</h2>

<form method="POST" class="post-job-form">
<?php echo csrfInput(); ?>

<input type="text" name="title" placeholder="Job Title" required>

<textarea name="description" placeholder="Job Description" required></textarea>

<input type="text" name="location" placeholder="Location" required>

<select name="type" required>
<option value="">Select Job Type</option>
<option>Full-time</option>
<option>Part-time</option>
<option>Remote</option>
</select>

<input type="text" name="salary" placeholder="Salary (Optional)">

<button type="submit">Post Job</button>

</form>

<?php include "../includes/footer.php"; ?>