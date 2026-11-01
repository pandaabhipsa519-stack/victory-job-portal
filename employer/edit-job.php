<?php
require_once __DIR__ . "/../includes/session.php";
requireEmployer();

$userId = (int) $_SESSION['user_id'];
$ownerStmt = $conn->prepare("SELECT emp_id FROM employers WHERE user_id = ? LIMIT 1");
$ownerStmt->bind_param("i", $userId);
$ownerStmt->execute();
$employer = $ownerStmt->get_result()->fetch_assoc();
if (!$employer) {
    http_response_code(403);
    exit("Employer profile not found.");
}
$emp_id = (int) $employer['emp_id'];

/* GET JOB ID */
$requestedJobId = $_GET['id'] ?? "";
if (!is_string($requestedJobId) || !ctype_digit($requestedJobId) || (int) $requestedJobId < 1) {
    http_response_code(400);
    exit("Invalid job.");
}

$job_id = (int) $requestedJobId;

/* FETCH JOB */
$getJob = $conn->prepare("SELECT * FROM jobs WHERE job_id = ? AND emp_id = ?");
$getJob->bind_param("ii", $job_id, $emp_id);
$getJob->execute();
$job = $getJob->get_result()->fetch_assoc();

if(!$job){
    http_response_code(404);
    echo "Job not found.";
    exit();
}

/* UPDATE JOB */
if($_SERVER["REQUEST_METHOD"] == "POST"){
    requireCsrfPost();

    $title = inputString($_POST['title'] ?? "", 150);
    $description = inputString($_POST['description'] ?? "");
    $location = inputString($_POST['location'] ?? "", 100);
    $type = inputString($_POST['type'] ?? "", 20);
    $salary = inputString($_POST['salary'] ?? "", 50);
    if ($title === "" || $description === "" || $location === ""
        || !in_array($type, ["Full-time", "Part-time", "Remote"], true)) {
        http_response_code(400);
        exit("Please provide a valid title, description, location, and job type.");
    }

$stmt = $conn->prepare("
UPDATE jobs
SET title=?, description=?, location=?, job_type=?, salary=?
WHERE job_id=? AND emp_id=?
");

$stmt->bind_param("sssssii",
$title,$description,$location,$type,$salary,$job_id,$emp_id);

if($stmt->execute()){
    header("Location: manage-jobs.php", true, 303);
    exit();
}else{
    error_log("Job update failed: " . $stmt->error);
    http_response_code(500);
    exit("The job could not be updated.");
}

}
include __DIR__ . "/../includes/header.php";
?>

<h2>Edit Job</h2>

<form method="POST" class="post-job-form">
<?php echo csrfInput(); ?>

<input type="text" name="title" value="<?php echo e($job['title']); ?>" required>

<textarea name="description" required><?php echo e($job['description']); ?></textarea>

<input type="text" name="location" value="<?php echo e($job['location']); ?>" required>

<select name="type" required>
<option <?php if($job['job_type']=="Full-time") echo "selected"; ?>>Full-time</option>
<option <?php if($job['job_type']=="Part-time") echo "selected"; ?>>Part-time</option>
<option <?php if($job['job_type']=="Remote") echo "selected"; ?>>Remote</option>
</select>

<input type="text" name="salary" value="<?php echo e($job['salary']); ?>">

<button type="submit">Update Job</button>

</form>

<?php include __DIR__ . "/../includes/footer.php"; ?>