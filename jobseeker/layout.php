<?php
require_once "../includes/session.php";
require_once "../includes/config.php";

requireJobseeker();

$user_id = $_SESSION['user_id'];
$currentPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));

// get profile data
$stmt = $conn->prepare("
SELECT name, profile_pic
FROM job_seekers
WHERE user_id = ?
");

$stmt->bind_param("i",$user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$profile = "../assets/images/logo.png";

if (!empty($user['profile_pic'])) {
    $profile = "../uploads/profile/" . basename((string) $user['profile_pic']);
}
?>

<div class="dashboard student-dashboard-shell">

<!-- Sidebar -->
<?php if ($currentPage === 'dashboard.php'): ?>
<aside class="sidebar" aria-label="Student navigation">
<div class="sidebar-profile">
<img src="<?php echo e($profile); ?>"
width="80" height="80" alt="">
<h3><?php echo e($user['name'] ?? 'Job seeker'); ?></h3>
<p>Job seeker</p>
</div>
<nav class="sidebar-navigation" aria-label="Student dashboard">
<a href="dashboard.php"<?php echo $currentPage === 'dashboard.php' ? ' aria-current="page"' : ''; ?>>🏠 <span>Dashboard</span></a>
<a href="profile.php">👤 <span>My Profile</span></a>
<a href="search-jobs.php">🔍 <span>Search Jobs</span></a>
<a href="my-applications.php">📄 <span>My Applications</span></a>
<a href="saved-jobs.php">⭐ <span>Saved Jobs</span></a>
<a href="notifications.php">🔔 <span>Notifications</span></a>
</nav>
<form class="sidebar-logout-form" method="POST" action="../auth/logout.php">
<?php echo csrfInput(); ?>
<button type="submit"><span aria-hidden="true">🚪</span> Logout</button>
</form>
</aside>
<?php else: ?>
<div class="sidebar">
<div style="text-align:center;margin-bottom:20px;">
<img src="<?php echo e($profile); ?>"
style="width:80px;height:80px;border-radius:50%;object-fit:cover;">
<h3><?php echo e($user['name']); ?></h3>
</div>
<a href="dashboard.php">🏠 Dashboard</a>
<a href="profile.php">👤 My Profile</a>
<a href="search-jobs.php">🔍 Search Jobs</a>
<a href="my-applications.php">📄 My Applications</a>
<a href="saved-jobs.php">⭐ Saved Jobs</a>
<a href="notifications.php">🔔 Notifications</a>
<form method="POST" action="../auth/logout.php">
<?php echo csrfInput(); ?>
<button type="submit">🚪 Logout</button>
</form>
</div>
<?php endif; ?>

<!-- Content -->
<div class="dashboard-content"<?php echo $currentPage === 'dashboard.php' ? ' role="main"' : ''; ?>>