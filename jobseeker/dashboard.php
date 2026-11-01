<?php
define('VICTORY_STUDENT_DASHBOARD_PAGE', true);
include "../includes/header.php";
include "layout.php";
require_once "../includes/config.php";

$user_id = (int) $_SESSION['user_id'];

$profileStmt = $conn->prepare("
SELECT name, phone, skills, location, about, profile_pic, resume
FROM job_seekers
WHERE user_id = ?
");
$profileStmt->bind_param("i", $user_id);
$profileStmt->execute();
$profile = $profileStmt->get_result()->fetch_assoc() ?: [];

$total = 7;
$filled = 0;
foreach (['name', 'phone', 'location', 'about', 'profile_pic', 'resume'] as $field) {
    if (is_string($profile[$field] ?? null) && trim($profile[$field]) !== '') {
        $filled++;
    }
}

$skillsCountStmt = $conn->prepare("
SELECT COUNT(*) AS total
FROM student_skills ss
JOIN job_seekers js ON js.js_id = ss.js_id
WHERE js.user_id = ?
");
$skillsCountStmt->bind_param("i", $user_id);
$skillsCountStmt->execute();
$savedSkillCount = (int) $skillsCountStmt->get_result()->fetch_assoc()['total'];
if ($savedSkillCount > 0) {
    $filled++;
}

$percent = (int) round(($filled / $total) * 100);

$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM applications WHERE user_id = ?");
$countStmt->bind_param("i", $user_id);
$countStmt->execute();
$appCount = (int) $countStmt->get_result()->fetch_assoc()['total'];

$savedCountStmt = $conn->prepare("SELECT COUNT(*) AS total FROM saved_jobs WHERE user_id = ?");
$savedCountStmt->bind_param("i", $user_id);
$savedCountStmt->execute();
$savedCount = (int) $savedCountStmt->get_result()->fetch_assoc()['total'];

$skills = $profile['skills'] ?? "";
$location = $profile['location'] ?? "";
$jobStmt = $conn->prepare("
SELECT jobs.job_id, jobs.title, jobs.location, jobs.posted_date, employers.company_name
FROM jobs
JOIN employers ON jobs.emp_id = employers.emp_id
JOIN users ON users.user_id = employers.user_id
LEFT JOIN companies ON companies.company_id = employers.company_id
WHERE users.account_status = 'active'
AND (employers.company_id IS NULL OR companies.status = 'active')
AND (jobs.description LIKE ? OR jobs.location LIKE ?)
ORDER BY jobs.posted_date DESC
LIMIT 3
");
$skillSearch = "%" . $skills . "%";
$locationSearch = "%" . $location . "%";
$jobStmt->bind_param("ss", $skillSearch, $locationSearch);
$jobStmt->execute();
$jobs = $jobStmt->get_result();

$savedStmt = $conn->prepare("
SELECT jobs.job_id, jobs.title, jobs.location, employers.company_name
FROM saved_jobs
JOIN jobs ON saved_jobs.job_id = jobs.job_id
JOIN employers ON jobs.emp_id = employers.emp_id
JOIN users ON users.user_id = employers.user_id
LEFT JOIN companies ON companies.company_id = employers.company_id
WHERE saved_jobs.user_id = ?
AND users.account_status = 'active'
AND (employers.company_id IS NULL OR companies.status = 'active')
ORDER BY saved_jobs.saved_date DESC
LIMIT 3
");
$savedStmt->bind_param("i", $user_id);
$savedStmt->execute();
$savedJobs = $savedStmt->get_result();

$recentStmt = $conn->prepare("
SELECT jobs.job_id, jobs.title, applications.status, applications.applied_date
FROM applications
JOIN jobs ON applications.job_id = jobs.job_id
WHERE applications.user_id = ?
ORDER BY applied_date DESC
LIMIT 5
");
$recentStmt->bind_param("i", $user_id);
$recentStmt->execute();
$recent = $recentStmt->get_result();
?>

<div class="student-dashboard-page">
    <section class="dashboard-welcome" aria-labelledby="student-dashboard-title">
        <div>
            <p class="eyebrow">Your career overview</p>
            <h1 id="student-dashboard-title">Welcome back, <?php echo e(!empty($profile['name']) ? $profile['name'] : 'Job seeker'); ?></h1>
            <p>Keep your profile current and find opportunities that match your experience.</p>
        </div>
        <a class="dashboard-primary-link" href="search-jobs.php">
            <i class="fa fa-magnifying-glass" aria-hidden="true"></i>
            Find jobs
        </a>
    </section>

    <section class="dashboard-stat-grid" aria-label="Your activity">
        <article class="dashboard-stat-card">
            <span class="dashboard-stat-icon" aria-hidden="true"><i class="fa fa-paper-plane"></i></span>
            <div>
                <p>Applications</p>
                <strong><?php echo $appCount; ?></strong>
                <a href="my-applications.php">Track your applications</a>
            </div>
        </article>
        <article class="dashboard-stat-card">
            <span class="dashboard-stat-icon" aria-hidden="true"><i class="fa fa-bookmark"></i></span>
            <div>
                <p>Saved jobs</p>
                <strong><?php echo $savedCount; ?></strong>
                <a href="saved-jobs.php">Review saved roles</a>
            </div>
        </article>
        <article class="dashboard-stat-card">
            <span class="dashboard-stat-icon" aria-hidden="true"><i class="fa fa-user-check"></i></span>
            <div>
                <p>Profile complete</p>
                <strong><?php echo $percent; ?>%</strong>
                <a href="profile.php">Improve your profile</a>
            </div>
        </article>
    </section>

    <div class="dashboard-content-grid">
        <div class="dashboard-main-column">
            <section class="dashboard-panel profile-completion-panel" aria-labelledby="profile-completion-title">
                <div class="dashboard-section-heading">
                    <div>
                        <p class="eyebrow">Stand out to employers</p>
                        <h2 id="profile-completion-title">Profile completion</h2>
                    </div>
                    <span class="completion-value"><?php echo $percent; ?>%</span>
                </div>
                <div class="completion-track" role="progressbar" aria-label="Profile completion"
                     aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100">
                    <span style="width: <?php echo $percent; ?>%"></span>
                </div>
                <div class="completion-footer">
                    <p>
                        <?php echo !empty($profile['resume']) ? 'Your resume is on file.' : 'No resume is on file yet.'; ?>
                    </p>
                    <a href="profile.php">Manage profile <span aria-hidden="true">→</span></a>
                </div>
            </section>

            <section class="dashboard-panel" aria-labelledby="recommendations-title">
                <div class="dashboard-section-heading">
                    <div>
                        <p class="eyebrow"><?php echo ($skills !== '' || $location !== '') ? 'Based on your profile' : 'Latest opportunities'; ?></p>
                        <h2 id="recommendations-title">Recommended jobs</h2>
                    </div>
                    <a class="dashboard-text-link" href="search-jobs.php">Browse all jobs <span aria-hidden="true">→</span></a>
                </div>

                <?php if ($jobs->num_rows > 0): ?>
                    <div class="dashboard-recommendations">
                        <?php while ($job = $jobs->fetch_assoc()): ?>
                            <article class="dashboard-job-card">
                                <span class="company-mark" aria-hidden="true"><i class="fa fa-building"></i></span>
                                <div class="dashboard-job-details">
                                    <h3><?php echo e($job['title']); ?></h3>
                                    <p class="dashboard-company"><?php echo e($job['company_name']); ?></p>
                                    <p class="dashboard-job-location"><i class="fa fa-location-dot" aria-hidden="true"></i> <?php echo e($job['location']); ?></p>
                                </div>
                                <div class="dashboard-job-actions">
                                    <a class="dashboard-secondary-link" href="../job-details.php?id=<?php echo (int) $job['job_id']; ?>">View details</a>
                                    <form method="POST" action="apply-job.php">
                                        <?php echo csrfInput(); ?>
                                        <input type="hidden" name="job_id" value="<?php echo (int) $job['job_id']; ?>">
                                        <button class="dashboard-apply-button" type="submit">Apply</button>
                                    </form>
                                    <form method="POST" action="save-job.php">
                                        <?php echo csrfInput(); ?>
                                        <input type="hidden" name="job_id" value="<?php echo (int) $job['job_id']; ?>">
                                        <button class="dashboard-save-button" type="submit" aria-label="Save <?php echo e($job['title']); ?>">
                                            <i class="fa fa-bookmark" aria-hidden="true"></i><span class="sr-only">Save job</span>
                                        </button>
                                    </form>
                                </div>
                            </article>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="dashboard-empty-state">
                        <i class="fa fa-magnifying-glass" aria-hidden="true"></i>
                        <p>No recommended jobs are available right now.</p>
                        <a href="search-jobs.php">Explore open jobs</a>
                    </div>
                <?php endif; ?>
            </section>

            <section class="dashboard-panel" aria-labelledby="recent-applications-title">
                <div class="dashboard-section-heading">
                    <div>
                        <p class="eyebrow">Stay up to date</p>
                        <h2 id="recent-applications-title">Recent applications</h2>
                    </div>
                    <a class="dashboard-text-link" href="my-applications.php">View all <span aria-hidden="true">→</span></a>
                </div>
                <?php if ($recent->num_rows > 0): ?>
                    <div class="dashboard-table-wrap">
                        <table class="app-table dashboard-app-table">
                            <thead>
                                <tr><th scope="col">Position</th><th scope="col">Status</th><th scope="col">Applied</th></tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $recent->fetch_assoc()): ?>
                                    <?php
                                    $statusClass = strtolower(trim((string) $row['status']));
                                    $statusClass = preg_replace('/[^a-z0-9]+/', '-', $statusClass);
                                    ?>
                                    <tr>
                                        <td><a href="../job-details.php?id=<?php echo (int) $row['job_id']; ?>"><?php echo e($row['title']); ?></a></td>
                                        <td><span class="application-status status-<?php echo e($statusClass !== '' ? $statusClass : 'unknown'); ?>"><?php echo e($row['status']); ?></span></td>
                                        <td><?php echo e($row['applied_date']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="dashboard-empty-state dashboard-empty-state-compact">
                        <p>You have not applied to any jobs yet.</p>
                        <a href="search-jobs.php">Find your first opportunity</a>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <aside class="dashboard-side-column" aria-label="Saved jobs">
            <section class="dashboard-panel saved-jobs-panel" aria-labelledby="saved-jobs-title">
                <div class="dashboard-section-heading">
                    <div>
                        <p class="eyebrow">Keep your shortlist close</p>
                        <h2 id="saved-jobs-title">Saved jobs</h2>
                    </div>
                    <a class="dashboard-text-link" href="saved-jobs.php">View all <span aria-hidden="true">→</span></a>
                </div>
                <?php if ($savedJobs->num_rows > 0): ?>
                    <div class="saved-job-list">
                        <?php while ($savedJob = $savedJobs->fetch_assoc()): ?>
                            <article class="saved-job-item">
                                <h3><a href="../job-details.php?id=<?php echo (int) $savedJob['job_id']; ?>"><?php echo e($savedJob['title']); ?></a></h3>
                                <p><?php echo e($savedJob['company_name']); ?></p>
                                <span><i class="fa fa-location-dot" aria-hidden="true"></i> <?php echo e($savedJob['location']); ?></span>
                            </article>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="dashboard-empty-state dashboard-empty-state-compact">
                        <p>No saved jobs yet. Save roles to revisit them here.</p>
                        <a href="search-jobs.php">Explore jobs</a>
                    </div>
                <?php endif; ?>
            </section>

            <section class="dashboard-panel dashboard-quick-links" aria-labelledby="quick-links-title">
                <h2 id="quick-links-title">Quick links</h2>
                <a href="profile.php"><i class="fa fa-user" aria-hidden="true"></i> Edit profile</a>
                <a href="notifications.php"><i class="fa fa-bell" aria-hidden="true"></i> Notifications</a>
                <a href="my-applications.php"><i class="fa fa-file-lines" aria-hidden="true"></i> Application history</a>
            </section>
        </aside>
    </div>
</div>

</div>
</div>

<?php include "../includes/footer.php"; ?>