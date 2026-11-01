<?php
require_once __DIR__ . "/session.php";
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/functions.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Victory | Find your next opportunity</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/victory/assets/css/style.css">
    <link rel="stylesheet" href="/victory/assets/css/design-system.css?v=20261031">
    <?php if (defined('VICTORY_STUDENT_DASHBOARD_PAGE') && VICTORY_STUDENT_DASHBOARD_PAGE === true): ?>
        <link rel="stylesheet" href="/victory/assets/css/student-dashboard.css?v=20261031-1">
    <?php endif; ?>
    <?php if (defined('VICTORY_EMPLOYER_DASHBOARD_PAGE') && VICTORY_EMPLOYER_DASHBOARD_PAGE === true): ?>
        <link rel="stylesheet" href="/victory/assets/css/employer-dashboard.css?v=20261031-1">
    <?php endif; ?>
    <?php if (defined('VICTORY_ADMIN_DASHBOARD_PAGE') && VICTORY_ADMIN_DASHBOARD_PAGE === true): ?>
        <link rel="stylesheet" href="/victory/assets/css/admin-dashboard.css?v=20261031-1">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <div id="loader" class="loader"></div>

<header class="site-header">
    <div class="site-header-inner">
    <a class="site-brand" href="/victory/index.php" aria-label="Victory home">
        <span class="site-brand-mark" aria-hidden="true">V</span>
        <span class="site-brand-name">Victory<small>CAREER NETWORK</small></span>
    </a>
    <button class="site-nav-toggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="primary-navigation">
        <i class="fa fa-bars" aria-hidden="true"></i>
    </button>
    <nav id="primary-navigation" class="site-nav" aria-label="Main navigation">
        <?php if (!empty($_SESSION['profile_pic'])): ?>
            <img src="/victory/uploads/profile/<?php echo e(basename((string) $_SESSION['profile_pic'])); ?>"
                 alt="Profile picture" class="site-avatar">
        <?php endif; ?>
        <a href="/victory/index.php"><i class="fa fa-home" aria-hidden="true"></i> Home</a>
        <a href="/victory/jobs.php"><i class="fa fa-briefcase" aria-hidden="true"></i> Jobs</a>
        <a href="/victory/about.php">About</a>
        <a href="/victory/contact.php">Contact</a>
        <?php if (!isLoggedIn()): ?>
            <a href="/victory/auth/login.php"><i class="fa fa-user" aria-hidden="true"></i> Login</a>
            <a href="/victory/auth/register.php"><i class="fa fa-user-plus" aria-hidden="true"></i> Register</a>

        <?php elseif ($_SESSION['role'] === 'jobseeker'): ?>

            <?php
            $uid = (int) $_SESSION['user_id'];

            $notificationCount = $conn->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ?");
            $notificationCount->bind_param("i", $uid);
            $notificationCount->execute();
            $result = $notificationCount->get_result();
            $count = $result->fetch_assoc()['total'];
            $bellClass = ($count > 0) ? 'bell-alert' : '';
            ?>

            <!-- Notification Bell -->
            <a href="/victory/jobseeker/notifications.php" id="bellIcon" class="nav-notifications <?php echo e($bellClass); ?>" aria-label="<?php echo (int) $count; ?> notifications">
                <i class="fa fa-bell" aria-hidden="true"></i> <span><?php echo (int) $count; ?></span>
            </a>

            <div id="notificationBox" class="notification-box">
            <?php
            $notificationList = $conn->prepare("SELECT message FROM notifications WHERE user_id = ? ORDER BY sent_date DESC LIMIT 5");
            $notificationList->bind_param("i", $uid);
            $notificationList->execute();
            $latest = $notificationList->get_result();

            while($note = $latest->fetch_assoc()):
            ?>
                <div class="notification-item">
                    <?php echo htmlspecialchars($note['message'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endwhile; ?>
            </div>

            <a href="/victory/jobseeker/dashboard.php"><i class="fa fa-user" aria-hidden="true"></i> Dashboard</a>
            <form class="nav-logout-form" method="POST" action="/victory/auth/logout.php">
                <?php echo csrfInput(); ?>
                <button type="submit"><i class="fa fa-sign-out" aria-hidden="true"></i> Logout</button>
            </form>

        <?php elseif ($_SESSION['role'] === 'employer'): ?>

            <a href="/victory/employer/dashboard.php"><i class="fa fa-user" aria-hidden="true"></i> Dashboard</a>
            <a href="/victory/employer/manage-jobs.php">Manage Jobs</a>
            <form class="nav-logout-form" method="POST" action="/victory/auth/logout.php">
                <?php echo csrfInput(); ?>
                <button type="submit"><i class="fa fa-sign-out" aria-hidden="true"></i> Logout</button>
            </form>

        <?php elseif ($_SESSION['role'] === 'admin'): ?>

            <a href="/victory/admin/dashboard.php"><i class="fa fa-chart-pie" aria-hidden="true"></i> Dashboard</a>
            <a href="/victory/admin/manage-students.php"><i class="fa fa-user-graduate" aria-hidden="true"></i> Students</a>
            <a href="/victory/admin/manage-employees.php"><i class="fa fa-user-tie" aria-hidden="true"></i> Professionals</a>
            <a href="/victory/admin/manage-employers.php"><i class="fa fa-users" aria-hidden="true"></i> Recruiters</a>
            <a href="/victory/admin/manage-companies.php"><i class="fa fa-building" aria-hidden="true"></i> Companies</a>
            <a href="/victory/admin/manage-jobs.php"><i class="fa fa-briefcase" aria-hidden="true"></i> Jobs</a>
            <a href="/victory/admin/manage-applications.php"><i class="fa fa-file-lines" aria-hidden="true"></i> Applications</a>
            <form class="nav-logout-form" method="POST" action="/victory/auth/logout.php">
                <?php echo csrfInput(); ?>
                <button type="submit"><i class="fa fa-sign-out" aria-hidden="true"></i> Logout</button>
            </form>

        <?php endif; ?>
    </nav>

    <div class="site-header-actions">
        <button class="icon-button" type="button" onclick="toggleDarkMode()" aria-label="Toggle dark mode" title="Toggle dark mode">
            <i class="fa fa-circle-half-stroke" aria-hidden="true"></i>
        </button>
        <button class="icon-button" type="button" onclick="toggleSettings()" aria-label="Open settings" title="Settings">
            <i class="fa fa-gear" aria-hidden="true"></i>
        </button>
    </div>
    </div>
</header>

<div class="container">