<?php
require_once __DIR__ . "/../includes/session.php";
requireEmployer();
require_once __DIR__ . "/../includes/config.php";

$userId = (int) $_SESSION['user_id'];
$employerStmt = $conn->prepare("
    SELECT emp_id, company_name, industry, location
    FROM employers
    WHERE user_id = ?
    LIMIT 1
");
$employerStmt->bind_param("i", $userId);
$employerStmt->execute();
$employer = $employerStmt->get_result()->fetch_assoc();

if (!$employer) {
    http_response_code(403);
    exit("Employer profile not found.");
}

$employerId = (int) $employer['emp_id'];
$companyName = trim((string) $employer['company_name']);

$jobsCountStmt = $conn->prepare("SELECT COUNT(*) AS total FROM jobs WHERE emp_id = ?");
$jobsCountStmt->bind_param("i", $employerId);
$jobsCountStmt->execute();
$totalJobs = (int) $jobsCountStmt->get_result()->fetch_assoc()['total'];

$applicationsCountStmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM applications
    JOIN jobs ON jobs.job_id = applications.job_id
    WHERE jobs.emp_id = ?
");
$applicationsCountStmt->bind_param("i", $employerId);
$applicationsCountStmt->execute();
$totalApplications = (int) $applicationsCountStmt->get_result()->fetch_assoc()['total'];

$candidatesCountStmt = $conn->prepare("
    SELECT COUNT(DISTINCT applications.user_id) AS total
    FROM applications
    JOIN jobs ON jobs.job_id = applications.job_id
    WHERE jobs.emp_id = ?
");
$candidatesCountStmt->bind_param("i", $employerId);
$candidatesCountStmt->execute();
$totalCandidates = (int) $candidatesCountStmt->get_result()->fetch_assoc()['total'];

$recentJobsStmt = $conn->prepare("
    SELECT
        jobs.job_id,
        jobs.title,
        jobs.location,
        jobs.job_type,
        jobs.salary,
        jobs.posted_date,
        (
            SELECT COUNT(*)
            FROM applications
            WHERE applications.job_id = jobs.job_id
        ) AS application_count
    FROM jobs
    WHERE jobs.emp_id = ?
    ORDER BY jobs.posted_date DESC
    LIMIT 5
");
$recentJobsStmt->bind_param("i", $employerId);
$recentJobsStmt->execute();
$recentJobs = $recentJobsStmt->get_result();

$recentApplicantsStmt = $conn->prepare("
    SELECT
        applications.application_id,
        applications.job_id,
        applications.status,
        applications.applied_date,
        jobs.title,
        job_seekers.name,
        job_seekers.skills
    FROM applications
    JOIN jobs ON jobs.job_id = applications.job_id
    JOIN job_seekers ON job_seekers.user_id = applications.user_id
    WHERE jobs.emp_id = ?
    ORDER BY applications.applied_date DESC, applications.application_id DESC
    LIMIT 5
");
$recentApplicantsStmt->bind_param("i", $employerId);
$recentApplicantsStmt->execute();
$recentApplicants = $recentApplicantsStmt->get_result();

define('VICTORY_EMPLOYER_DASHBOARD_PAGE', true);
include __DIR__ . "/../includes/header.php";
?>

<div class="employer-dashboard">
    <aside class="employer-dashboard-sidebar" aria-label="Employer navigation">
        <div class="employer-company-card">
            <span class="employer-company-mark" aria-hidden="true">
                <i class="fa fa-building"></i>
            </span>
            <h2><?php echo e($companyName !== '' ? $companyName : 'Your company'); ?></h2>
            <?php if (!empty($employer['industry'])): ?>
                <p><?php echo e($employer['industry']); ?></p>
            <?php endif; ?>
            <?php if (!empty($employer['location'])): ?>
                <span><i class="fa fa-location-dot" aria-hidden="true"></i> <?php echo e($employer['location']); ?></span>
            <?php endif; ?>
        </div>

        <nav class="employer-dashboard-nav" aria-label="Employer dashboard">
            <a href="dashboard.php" aria-current="page"><i class="fa fa-chart-line" aria-hidden="true"></i> Dashboard</a>
            <a href="post-job.php"><i class="fa fa-circle-plus" aria-hidden="true"></i> Post a job</a>
            <a href="manage-jobs.php"><i class="fa fa-briefcase" aria-hidden="true"></i> Manage jobs</a>
            <a href="view-applicants.php"><i class="fa fa-users" aria-hidden="true"></i> Applicants</a>
        </nav>

        <form class="employer-sidebar-logout" method="POST" action="../auth/logout.php">
            <?php echo csrfInput(); ?>
            <button type="submit"><i class="fa fa-right-from-bracket" aria-hidden="true"></i> Log out</button>
        </form>
    </aside>

    <main class="employer-dashboard-main">
        <section class="employer-welcome" aria-labelledby="employer-welcome-title">
            <div>
                <p class="eyebrow">Employer workspace</p>
                <h1 id="employer-welcome-title">Welcome<?php echo $companyName !== '' ? ', ' . e($companyName) : ''; ?></h1>
                <p>Manage your job listings and connect with candidates from one place.</p>
            </div>
            <a class="employer-primary-action" href="post-job.php">
                <i class="fa fa-plus" aria-hidden="true"></i> Post a job
            </a>
        </section>

        <section class="employer-stat-grid" aria-label="Employer activity">
            <article class="employer-stat-card">
                <span class="employer-stat-icon" aria-hidden="true"><i class="fa fa-briefcase"></i></span>
                <div>
                    <p>Jobs posted</p>
                    <strong><?php echo $totalJobs; ?></strong>
                    <a href="manage-jobs.php">Manage listings</a>
                </div>
            </article>
            <article class="employer-stat-card">
                <span class="employer-stat-icon" aria-hidden="true"><i class="fa fa-file-lines"></i></span>
                <div>
                    <p>Applications</p>
                    <strong><?php echo $totalApplications; ?></strong>
                    <a href="view-applicants.php">Review applications</a>
                </div>
            </article>
            <article class="employer-stat-card">
                <span class="employer-stat-icon" aria-hidden="true"><i class="fa fa-user-group"></i></span>
                <div>
                    <p>Unique candidates</p>
                    <strong><?php echo $totalCandidates; ?></strong>
                    <a href="view-applicants.php">View candidates</a>
                </div>
            </article>
        </section>

        <section class="employer-dashboard-panel" aria-labelledby="employer-jobs-title">
            <div class="employer-section-heading">
                <div>
                    <p class="eyebrow">Your opportunities</p>
                    <h2 id="employer-jobs-title">Recent job listings</h2>
                </div>
                <a class="employer-section-link" href="manage-jobs.php">Manage all <span aria-hidden="true">→</span></a>
            </div>

            <?php if ($recentJobs->num_rows > 0): ?>
                <div class="employer-job-list">
                    <?php while ($job = $recentJobs->fetch_assoc()): ?>
                        <article class="employer-job-card">
                            <span class="employer-job-icon" aria-hidden="true"><i class="fa fa-briefcase"></i></span>
                            <div class="employer-job-details">
                                <h3><?php echo e($job['title']); ?></h3>
                                <div class="employer-job-meta">
                                    <span><i class="fa fa-location-dot" aria-hidden="true"></i> <?php echo e($job['location']); ?></span>
                                    <span><i class="fa fa-clock" aria-hidden="true"></i> <?php echo e($job['job_type']); ?></span>
                                    <span><i class="fa fa-users" aria-hidden="true"></i> <?php echo (int) $job['application_count']; ?> applicants</span>
                                </div>
                            </div>
                            <div class="employer-job-actions">
                                <a href="../job-details.php?id=<?php echo (int) $job['job_id']; ?>">View job</a>
                                <a href="edit-job.php?id=<?php echo (int) $job['job_id']; ?>">Edit</a>
                                <a href="manage-jobs.php">Manage</a>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="employer-empty-state">
                    <i class="fa fa-briefcase" aria-hidden="true"></i>
                    <p>No job listings yet. Create your first listing to reach candidates.</p>
                    <a class="employer-primary-action" href="post-job.php">Post a job</a>
                </div>
            <?php endif; ?>
        </section>

        <section class="employer-dashboard-panel" aria-labelledby="employer-applicants-title">
            <div class="employer-section-heading">
                <div>
                    <p class="eyebrow">Candidate pipeline</p>
                    <h2 id="employer-applicants-title">Recent applicants</h2>
                </div>
                <a class="employer-section-link" href="view-applicants.php">View all applicants <span aria-hidden="true">→</span></a>
            </div>

            <?php if ($recentApplicants->num_rows > 0): ?>
                <div class="employer-applicant-list">
                    <?php while ($applicant = $recentApplicants->fetch_assoc()): ?>
                        <?php
                        $statusClass = strtolower(trim((string) $applicant['status']));
                        $statusClass = preg_replace('/[^a-z0-9]+/', '-', $statusClass);
                        $allowedStatuses = ["Pending", "Shortlisted", "Rejected", "Hired"];
                        ?>
                        <article class="employer-applicant-card">
                            <span class="employer-candidate-icon" aria-hidden="true"><i class="fa fa-user"></i></span>
                            <div class="employer-applicant-details">
                                <h3><?php echo e($applicant['name']); ?></h3>
                                <p>Applied for <strong><?php echo e($applicant['title']); ?></strong></p>
                                <?php if (!empty($applicant['skills'])): ?>
                                    <p class="employer-candidate-skills"><?php echo e($applicant['skills']); ?></p>
                                <?php endif; ?>
                                <span class="employer-applied-date">Applied <?php echo e($applicant['applied_date']); ?></span>
                            </div>
                            <div class="employer-applicant-actions">
                                <span class="employer-status-badge status-<?php echo e($statusClass !== '' ? $statusClass : 'unknown'); ?>">
                                    <?php echo e($applicant['status']); ?>
                                </span>
                                <?php if (in_array($applicant['status'], $allowedStatuses, true)): ?>
                                    <form method="POST" action="update-status.php" class="employer-status-form">
                                        <?php echo csrfInput(); ?>
                                        <input type="hidden" name="application_id" value="<?php echo (int) $applicant['application_id']; ?>">
                                        <label class="sr-only" for="applicant-status-<?php echo (int) $applicant['application_id']; ?>">Update status for <?php echo e($applicant['name']); ?></label>
                                        <select id="applicant-status-<?php echo (int) $applicant['application_id']; ?>" name="status" required>
                                            <option value="" selected disabled>Update status</option>
                                            <?php foreach ($allowedStatuses as $status): ?>
                                                <?php if ($status !== $applicant['status']): ?>
                                                    <option value="<?php echo e($status); ?>"><?php echo e($status); ?></option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit">Update</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="employer-empty-state">
                    <i class="fa fa-user-group" aria-hidden="true"></i>
                    <p>No applicants yet. New applications will appear here.</p>
                    <a href="manage-jobs.php">Review your job listings</a>
                </div>
            <?php endif; ?>
        </section>

        <section class="employer-quick-actions" aria-label="Quick actions">
            <a href="post-job.php"><i class="fa fa-circle-plus" aria-hidden="true"></i><span><strong>Post a job</strong><small>Create a new opportunity</small></span></a>
            <a href="manage-jobs.php"><i class="fa fa-list-check" aria-hidden="true"></i><span><strong>Manage jobs</strong><small>Edit or remove your listings</small></span></a>
            <a href="view-applicants.php"><i class="fa fa-users" aria-hidden="true"></i><span><strong>Review candidates</strong><small>Explore your applicant pipeline</small></span></a>
        </section>
    </main>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
