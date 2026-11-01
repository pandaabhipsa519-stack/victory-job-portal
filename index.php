<?php
include "includes/header.php";

$siteStats = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM job_seekers js JOIN users u ON u.user_id = js.user_id WHERE u.account_status = 'active') AS seeker_count,
        (SELECT COUNT(*) FROM companies WHERE status = 'active') AS company_count,
        (SELECT COUNT(*) FROM jobs j JOIN employers e ON e.emp_id = j.emp_id JOIN users u ON u.user_id = e.user_id LEFT JOIN companies c ON c.company_id = e.company_id WHERE u.account_status = 'active' AND (e.company_id IS NULL OR c.status = 'active')) AS job_count
")->fetch_assoc();
$categories = $conn->query("SELECT name FROM categories ORDER BY name LIMIT 8");
?>

<main class="home-main">
<section class="hero-modern">
    <p class="eyebrow">Your next opportunity starts here</p>
    <h1>Find work that moves your career forward.</h1>
    <p>Search current openings and connect with employers looking for your skills.</p>

    <form action="jobs.php" method="GET" class="search-box">
        <label class="sr-only" for="home-keyword">Job title or keyword</label>
        <input id="home-keyword" type="text" name="keyword" placeholder="Job title or keyword">
        <label class="sr-only" for="home-location">Location</label>
        <input id="home-location" type="text" name="location" placeholder="Location">

        <label class="sr-only" for="home-type">Job type</label>
        <select id="home-type" name="type">
            <option value="">All Types</option>
            <option>Full-time</option>
            <option>Part-time</option>
            <option>Remote</option>
        </select>

        <button type="submit">Find Jobs</button>
    </form>
</section>
<section class="stats reveal" aria-label="Current portal totals">
    <div class="stat-box">
        <h2 class="counter" data-target="<?php echo (int) $siteStats['seeker_count']; ?>">0</h2>
        <p>Registered job seekers</p>
    </div>

    <div class="stat-box">
        <h2 class="counter" data-target="<?php echo (int) $siteStats['company_count']; ?>">0</h2>
        <p>Registered companies</p>
    </div>

    <div class="stat-box">
        <h2 class="counter" data-target="<?php echo (int) $siteStats['job_count']; ?>">0</h2>
        <p>Posted opportunities</p>
    </div>
</section>

<section class="featured-jobs reveal">
    <div class="section-heading">
        <div>
            <h2>Latest opportunities</h2>
            <p>Explore recently posted roles from employers on Victory.</p>
        </div>
        <a href="/victory/jobs.php">Browse all jobs <span aria-hidden="true">→</span></a>
    </div>

    <div class="job-grid">

    <?php
    $jobs = $conn->query("
    SELECT jobs.*, employers.company_name
    FROM jobs
    JOIN employers ON jobs.emp_id = employers.emp_id
    JOIN users ON users.user_id = employers.user_id
    LEFT JOIN companies ON companies.company_id = employers.company_id
    WHERE users.account_status = 'active'
      AND (employers.company_id IS NULL OR companies.status = 'active')
    ORDER BY posted_date DESC
    LIMIT 6
");
?>

    <?php if($jobs->num_rows > 0): ?>
    <?php while($job = $jobs->fetch_assoc()): ?>

        <article class="job-card-modern">
            <h3><?php echo e($job['title']); ?></h3>
            <p><?php echo e($job['company_name']); ?></p>
            <div class="job-card-meta">
                <span><?php echo e($job['location'] ?? 'Location not specified'); ?></span>
                <span><?php echo e($job['job_type'] ?? 'Job'); ?></span>
            </div>
            <a href="job-details.php?id=<?php echo (int) $job['job_id']; ?>">View details <span aria-hidden="true">→</span></a>
        </article>

    <?php endwhile; ?>
<?php else: ?>
    <p>No jobs available yet.</p>
<?php endif; ?>
    </div>
</section>

<?php if ($categories->num_rows > 0): ?>
<section class="categories reveal">
    <h2>Browse categories</h2>

    <div class="category-grid">
        <?php while ($category = $categories->fetch_assoc()): ?>
            <div class="category-box"><?php echo e($category['name']); ?></div>
        <?php endwhile; ?>
    </div>
</section>
<?php endif; ?>
</main>

<?php include "includes/footer.php"; ?>