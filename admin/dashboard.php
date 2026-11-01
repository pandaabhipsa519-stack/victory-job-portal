<?php
require_once __DIR__ . "/../includes/session.php";
requireAdmin();
require_once __DIR__ . "/../includes/config.php";

$totalUsers = (int) $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
$totalStudents = (int) $conn->query("SELECT COUNT(*) AS total FROM job_seekers WHERE profile_type = 'student'")->fetch_assoc()['total'];
$totalEmployers = (int) $conn->query("SELECT COUNT(*) AS total FROM employers")->fetch_assoc()['total'];
$totalJobs = (int) $conn->query("SELECT COUNT(*) AS total FROM jobs")->fetch_assoc()['total'];
$totalApplications = (int) $conn->query("SELECT COUNT(*) AS total FROM applications")->fetch_assoc()['total'];

$applicationStatuses = $conn->query("
    SELECT status, COUNT(*) AS total
    FROM applications
    GROUP BY status
    ORDER BY total DESC, status ASC
");
$statusBreakdown = [];
while ($status = $applicationStatuses->fetch_assoc()) {
    $statusBreakdown[] = [
        'name' => (string) $status['status'],
        'total' => (int) $status['total'],
    ];
}

$recentJobs = $conn->query("
    SELECT jobs.job_id, jobs.title, jobs.location, jobs.job_type, jobs.posted_date,
           employers.company_name
    FROM jobs
    JOIN employers ON employers.emp_id = jobs.emp_id
    ORDER BY jobs.posted_date DESC, jobs.job_id DESC
    LIMIT 5
");

$recentApplications = $conn->query("
    SELECT applications.application_id, applications.status, applications.applied_date,
           jobs.title, employers.company_name, job_seekers.name AS candidate_name
    FROM applications
    JOIN jobs ON jobs.job_id = applications.job_id
    JOIN employers ON employers.emp_id = jobs.emp_id
    LEFT JOIN job_seekers ON job_seekers.user_id = applications.user_id
    ORDER BY applications.applied_date DESC, applications.application_id DESC
    LIMIT 5
");

define('VICTORY_ADMIN_DASHBOARD_PAGE', true);
include __DIR__ . "/../includes/header.php";
?>

<div class="admin-dashboard">
    <aside class="admin-dashboard-sidebar" aria-label="Admin navigation">
        <a class="admin-sidebar-brand" href="dashboard.php">
            <span aria-hidden="true">V</span>
            <span>Victory<small>ADMIN CONSOLE</small></span>
        </a>

        <nav class="admin-dashboard-nav" aria-label="Admin sections">
            <a href="dashboard.php" aria-current="page"><i class="fa fa-chart-pie" aria-hidden="true"></i> Dashboard</a>
            <a href="manage-students.php"><i class="fa fa-user-graduate" aria-hidden="true"></i> Students</a>
            <a href="manage-employees.php"><i class="fa fa-user-tie" aria-hidden="true"></i> Professionals</a>
            <a href="manage-employers.php"><i class="fa fa-user-group" aria-hidden="true"></i> Recruiters</a>
            <a href="manage-companies.php"><i class="fa fa-building" aria-hidden="true"></i> Companies</a>
            <a href="manage-jobs.php"><i class="fa fa-briefcase" aria-hidden="true"></i> Jobs</a>
            <a href="manage-applications.php"><i class="fa fa-file-lines" aria-hidden="true"></i> Applications</a>
            <a href="manage-categories.php"><i class="fa fa-layer-group" aria-hidden="true"></i> Categories</a>
            <a href="../index.php"><i class="fa fa-arrow-up-right-from-square" aria-hidden="true"></i> View site</a>
        </nav>

        <form class="admin-sidebar-logout" method="POST" action="../auth/logout.php">
            <?php echo csrfInput(); ?>
            <button type="submit"><i class="fa fa-right-from-bracket" aria-hidden="true"></i> Log out</button>
        </form>
    </aside>

    <main class="admin-dashboard-main">
        <section class="admin-welcome" aria-labelledby="admin-welcome-title">
            <div>
                <p class="eyebrow">Platform overview</p>
                <h1 id="admin-welcome-title">Admin dashboard</h1>
                <p>Monitor Victory activity and manage the people, companies, and opportunities on the platform.</p>
            </div>
            <a class="admin-primary-action" href="account-form.php?type=student">
                <i class="fa fa-users" aria-hidden="true"></i> Manage users
            </a>
        </section>

        <section class="admin-stat-grid" aria-label="Platform statistics">
            <article class="admin-stat-card">
                <span class="admin-stat-icon" aria-hidden="true"><i class="fa fa-users"></i></span>
                <div><p>Total users</p><strong><?php echo $totalUsers; ?></strong><a href="manage-users.php">Manage accounts</a></div>
            </article>
            <article class="admin-stat-card">
                <span class="admin-stat-icon" aria-hidden="true"><i class="fa fa-user-graduate"></i></span>
                <div><p>Students</p><strong><?php echo $totalStudents; ?></strong><a href="manage-students.php">View students</a></div>
            </article>
            <article class="admin-stat-card">
                <span class="admin-stat-icon" aria-hidden="true"><i class="fa fa-building"></i></span>
                <div><p>Recruiters</p><strong><?php echo $totalEmployers; ?></strong><a href="manage-employers.php">Manage recruiters</a></div>
            </article>
            <article class="admin-stat-card">
                <span class="admin-stat-icon" aria-hidden="true"><i class="fa fa-briefcase"></i></span>
                <div><p>Jobs</p><strong><?php echo $totalJobs; ?></strong><a href="manage-jobs.php">Manage listings</a></div>
            </article>
            <article class="admin-stat-card">
                <span class="admin-stat-icon" aria-hidden="true"><i class="fa fa-file-lines"></i></span>
                <div><p>Applications</p><strong><?php echo $totalApplications; ?></strong><a href="manage-applications.php">Review activity</a></div>
            </article>
        </section>

        <div class="admin-dashboard-content-grid">
            <div class="admin-dashboard-primary-column">
                <section class="admin-dashboard-panel" aria-labelledby="admin-applications-title">
                    <div class="admin-section-heading">
                        <div>
                            <p class="eyebrow">Reports &amp; analytics</p>
                            <h2 id="admin-applications-title">Application overview</h2>
                        </div>
                        <a class="admin-section-link" href="manage-applications.php">All applications <span aria-hidden="true">→</span></a>
                    </div>

                    <?php if ($totalApplications > 0): ?>
                        <div class="admin-status-chart" role="img" aria-label="Application status breakdown">
                            <?php foreach ($statusBreakdown as $status): ?>
                                <?php
                                $statusClass = strtolower(trim($status['name']));
                                $statusClass = preg_replace('/[^a-z0-9]+/', '-', $statusClass);
                                $segmentWidth = ($status['total'] / $totalApplications) * 100;
                                ?>
                                <span class="admin-status-segment status-<?php echo e($statusClass !== '' ? $statusClass : 'unknown'); ?>"
                                      style="width: <?php echo e(number_format($segmentWidth, 4, '.', '')); ?>%"></span>
                            <?php endforeach; ?>
                        </div>
                        <ul class="admin-status-legend">
                            <?php foreach ($statusBreakdown as $status): ?>
                                <?php
                                $statusClass = strtolower(trim($status['name']));
                                $statusClass = preg_replace('/[^a-z0-9]+/', '-', $statusClass);
                                ?>
                                <li>
                                    <span class="admin-status-dot status-<?php echo e($statusClass !== '' ? $statusClass : 'unknown'); ?>" aria-hidden="true"></span>
                                    <span><?php echo e($status['name']); ?></span>
                                    <strong><?php echo $status['total']; ?></strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="admin-empty-state">
                            <i class="fa fa-chart-simple" aria-hidden="true"></i>
                            <p>Application analytics will appear when candidates apply to jobs.</p>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="admin-dashboard-panel" aria-labelledby="admin-recent-applications-title">
                    <div class="admin-section-heading">
                        <div>
                            <p class="eyebrow">Latest platform activity</p>
                            <h2 id="admin-recent-applications-title">Recent applications</h2>
                        </div>
                        <a class="admin-section-link" href="manage-applications.php">View all <span aria-hidden="true">→</span></a>
                    </div>

                    <?php if ($recentApplications->num_rows > 0): ?>
                        <div class="admin-table-wrap">
                            <table class="admin-data-table">
                                <thead>
                                    <tr><th scope="col">Candidate</th><th scope="col">Position &amp; employer</th><th scope="col">Status</th><th scope="col">Applied</th></tr>
                                </thead>
                                <tbody>
                                    <?php while ($application = $recentApplications->fetch_assoc()): ?>
                                        <?php
                                        $statusClass = strtolower(trim((string) $application['status']));
                                        $statusClass = preg_replace('/[^a-z0-9]+/', '-', $statusClass);
                                        ?>
                                        <tr>
                                            <td><?php echo e($application['candidate_name'] ?: 'Candidate'); ?></td>
                                            <td><strong><?php echo e($application['title']); ?></strong><small><?php echo e($application['company_name']); ?></small></td>
                                            <td><span class="admin-status-badge status-<?php echo e($statusClass !== '' ? $statusClass : 'unknown'); ?>"><?php echo e($application['status']); ?></span></td>
                                            <td><?php echo e($application['applied_date']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="admin-empty-state admin-empty-state-compact">
                            <p>No applications have been submitted yet.</p>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <aside class="admin-dashboard-secondary-column" aria-label="Recent job listings">
                <section class="admin-dashboard-panel" aria-labelledby="admin-recent-jobs-title">
                    <div class="admin-section-heading">
                        <div>
                            <p class="eyebrow">Opportunity activity</p>
                            <h2 id="admin-recent-jobs-title">Recent jobs</h2>
                        </div>
                        <a class="admin-section-link" href="manage-jobs.php">Manage <span aria-hidden="true">→</span></a>
                    </div>

                    <?php if ($recentJobs->num_rows > 0): ?>
                        <div class="admin-recent-job-list">
                            <?php while ($job = $recentJobs->fetch_assoc()): ?>
                                <article class="admin-recent-job">
                                    <span class="admin-job-icon" aria-hidden="true"><i class="fa fa-briefcase"></i></span>
                                    <div>
                                        <h3><?php echo e($job['title']); ?></h3>
                                        <p><?php echo e($job['company_name']); ?></p>
                                        <span><?php echo e($job['location']); ?> · <?php echo e($job['job_type']); ?></span>
                                        <small>Posted <?php echo e($job['posted_date']); ?></small>
                                    </div>
                                </article>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="admin-empty-state admin-empty-state-compact">
                            <p>No jobs have been posted yet.</p>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="admin-dashboard-panel admin-management-panel" aria-labelledby="admin-management-title">
                    <div class="admin-section-heading">
                        <div>
                            <p class="eyebrow">Platform controls</p>
                            <h2 id="admin-management-title">Management</h2>
                        </div>
                    </div>
                    <a href="manage-students.php"><i class="fa fa-user-graduate" aria-hidden="true"></i><span>Students</span><i class="fa fa-arrow-right" aria-hidden="true"></i></a>
                    <a href="manage-employees.php"><i class="fa fa-user-tie" aria-hidden="true"></i><span>Professionals</span><i class="fa fa-arrow-right" aria-hidden="true"></i></a>
                    <a href="manage-employers.php"><i class="fa fa-users" aria-hidden="true"></i><span>Recruiters</span><i class="fa fa-arrow-right" aria-hidden="true"></i></a>
                    <a href="manage-companies.php"><i class="fa fa-building" aria-hidden="true"></i><span>Companies</span><i class="fa fa-arrow-right" aria-hidden="true"></i></a>
                    <a href="manage-jobs.php"><i class="fa fa-briefcase" aria-hidden="true"></i><span>Job listings</span><i class="fa fa-arrow-right" aria-hidden="true"></i></a>
                    <a href="manage-applications.php"><i class="fa fa-file-lines" aria-hidden="true"></i><span>Applications</span><i class="fa fa-arrow-right" aria-hidden="true"></i></a>
                    <a href="manage-categories.php"><i class="fa fa-layer-group" aria-hidden="true"></i><span>Categories</span><i class="fa fa-arrow-right" aria-hidden="true"></i></a>
                </section>
            </aside>
        </div>
    </main>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
