<?php
require_once __DIR__ . "/includes/session.php";

$keyword = inputString($_GET['keyword'] ?? "", 150);
$location = inputString($_GET['location'] ?? "", 100);
$type = inputString($_GET['type'] ?? "", 20);
$sort = inputString($_GET['sort'] ?? "", 30);
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
$page = $page === false ? 1 : $page;
$limit = 10;

$filters = [];
$filterValues = [];
$filterTypes = "";
if ($keyword !== "") {
    $filters[] = "(jobs.title LIKE ? OR jobs.description LIKE ?)";
    $pattern = "%" . $keyword . "%";
    $filterValues[] = $pattern;
    $filterValues[] = $pattern;
    $filterTypes .= "ss";
}
if ($location !== "") {
    $filters[] = "jobs.location LIKE ?";
    $filterValues[] = "%" . $location . "%";
    $filterTypes .= "s";
}
if (in_array($type, ["Full-time", "Part-time", "Remote"], true)) {
    $filters[] = "jobs.job_type = ?";
    $filterValues[] = $type;
    $filterTypes .= "s";
} else {
    $type = "";
}
$visibility = "users.account_status = 'active' AND (employers.company_id IS NULL OR companies.status = 'active')";
$where = " WHERE " . $visibility . ($filters ? " AND " . implode(" AND ", $filters) : "");

$order = match ($sort) {
    "salary_high" => "jobs.salary DESC, jobs.posted_date DESC",
    "salary_low" => "jobs.salary ASC, jobs.posted_date DESC",
    default => "jobs.posted_date DESC",
};

$countStmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM jobs
    JOIN employers ON jobs.emp_id = employers.emp_id
    JOIN users ON users.user_id = employers.user_id
    LEFT JOIN companies ON companies.company_id = employers.company_id
    $where
");
if (!$countStmt) {
    error_log("Job count query could not be prepared: " . $conn->error);
    http_response_code(500);
    exit("Jobs are temporarily unavailable.");
}
$countValues = $filterValues;
bindStatementParams($countStmt, $filterTypes, $countValues);
$countStmt->execute();
$totalJobs = (int) $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = max(1, (int) ceil($totalJobs / $limit));
$page = min($page, $totalPages);
$offset = ($page - 1) * $limit;

$jobStmt = $conn->prepare("
    SELECT jobs.job_id, jobs.title, jobs.job_type, jobs.salary, jobs.location, jobs.posted_date,
           employers.company_name
    FROM jobs
    JOIN employers ON jobs.emp_id = employers.emp_id
    JOIN users ON users.user_id = employers.user_id
    LEFT JOIN companies ON companies.company_id = employers.company_id
    $where
    ORDER BY $order
    LIMIT ? OFFSET ?
");
if (!$jobStmt) {
    error_log("Job search query could not be prepared: " . $conn->error);
    http_response_code(500);
    exit("Jobs are temporarily unavailable.");
}
$jobValues = $filterValues;
$jobValues[] = $limit;
$jobValues[] = $offset;
bindStatementParams($jobStmt, $filterTypes . "ii", $jobValues);
$jobStmt->execute();
$jobs = $jobStmt->get_result();

include __DIR__ . "/includes/header.php";
?>

<main class="page-section">
<div class="page-heading">
    <p class="eyebrow">Explore opportunities</p>
    <h1>Find your next role</h1>
    <p>Search current job listings by title, location, and job type.</p>
</div>
<form method="GET" action="jobs.php" class="job-search-form">
    <div class="job-search-field">
        <label for="search-keyword">Keyword or job title</label>
        <input id="search-keyword" type="text" name="keyword" placeholder="e.g. designer, analyst" value="<?php echo e($keyword); ?>">
    </div>
    <div class="job-search-field">
        <label for="search-location">Location</label>
        <input id="search-location" type="text" name="location" placeholder="City or region" value="<?php echo e($location); ?>">
    </div>
    <div class="job-search-field">
    <label for="search-type">Job type</label>
    <select id="search-type" name="type">
        <option value="">All Types</option>
        <?php foreach (["Full-time", "Part-time", "Remote"] as $jobType): ?>
            <option value="<?php echo e($jobType); ?>" <?php echo $type === $jobType ? "selected" : ""; ?>><?php echo e($jobType); ?></option>
        <?php endforeach; ?>
    </select>
    </div>
    <div class="job-search-field">
    <label for="search-sort">Sort by</label>
    <select id="search-sort" name="sort">
        <option value="">Newest</option>
        <option value="salary_high" <?php echo $sort === "salary_high" ? "selected" : ""; ?>>Salary High → Low</option>
        <option value="salary_low" <?php echo $sort === "salary_low" ? "selected" : ""; ?>>Salary Low → High</option>
    </select>
    </div>
    <button type="submit">Search jobs</button>
</form>

<input type="hidden" id="csrf-token" value="<?php echo e(csrfToken()); ?>">

<?php if ($jobs->num_rows > 0): ?>
    <div class="job-listing-grid">
    <?php while ($job = $jobs->fetch_assoc()): ?>
        <article class="job-card-modern">
            <h2><?php echo e($job['title']); ?></h2>
            <p><strong>Company:</strong> <?php echo e($job['company_name']); ?></p>
            <div class="job-card-meta">
                <span><?php echo e($job['location']); ?></span>
                <span><?php echo e($job['job_type']); ?></span>
            </div>
            <?php if ($job['salary'] !== null && $job['salary'] !== ''): ?>
                <p><strong>Salary:</strong> <?php echo e($job['salary']); ?></p>
            <?php endif; ?>
            <div class="job-card-actions">
                <a href="job-details.php?id=<?php echo (int) $job['job_id']; ?>">View details <span aria-hidden="true">→</span></a>
                <?php if (isLoggedIn() && $_SESSION['role'] === 'jobseeker'): ?>
                    <button type="button" class="save-job-button" data-job-id="<?php echo (int) $job['job_id']; ?>">Save job</button>
                <?php endif; ?>
            </div>
        </article>
    <?php endwhile; ?>
    </div>
<?php else: ?>
    <p>No jobs found. Try changing your search filters.</p>
<?php endif; ?>

<?php if ($totalPages > 1): ?>
    <nav aria-label="Job search pages">
        <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
            <?php $pageQuery = http_build_query(array_merge($_GET, ['page' => $pageNumber])); ?>
            <a href="?<?php echo e($pageQuery); ?>" <?php echo $pageNumber === $page ? 'aria-current="page"' : ''; ?>>
                <?php echo $pageNumber; ?>
            </a>
        <?php endfor; ?>
    </nav>
<?php endif; ?>

<script>
document.querySelectorAll(".save-job-button").forEach((button) => {
    button.addEventListener("click", async () => {
        button.disabled = true;
        try {
            const response = await fetch("/victory/save-job.php", {
                method: "POST",
                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                body: new URLSearchParams({
                    job_id: button.dataset.jobId,
                    csrf_token: document.getElementById("csrf-token").value
                })
            });
            if (!response.ok) {
                throw new Error("Save request failed.");
            }
            const result = await response.text();
            button.textContent = result === "already_saved" ? "Already saved" : "Saved";
        } catch (error) {
            button.disabled = false;
            alert("Could not save this job. Please try again.");
        }
    });
});
</script>

</main>
<?php include __DIR__ . "/includes/footer.php"; ?>
