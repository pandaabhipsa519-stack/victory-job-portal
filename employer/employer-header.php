<?php require_once __DIR__ . "/../includes/session.php"; requireEmployer(); ?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Victory Job Portal</title>

<!-- CSS -->
<link rel="stylesheet" href="/victory/assets/css/style.css">

<!-- Google Font -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

<!-- Font Awesome -->
<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>

<body>

<header class="main-header">

<!-- LEFT -->
<div class="logo">
<img src="/victory/assets/images/logo.png" alt="logo">
<span>Victory</span>
</div>

<!-- CENTER NAV -->
<nav>

<a href="/victory/index.php"><i class="fa fa-home"></i> Home</a>
<a href="/victory/jobs.php"><i class="fa fa-briefcase"></i> Jobs</a>

<?php if (!isLoggedIn()): ?>

<a href="/victory/auth/login.php"><i class="fa fa-user"></i> Login</a>
<a href="/victory/auth/register.php"><i class="fa fa-user-plus"></i> Register</a>

<?php endif; ?>

<?php if (isLoggedIn() && $_SESSION['role'] === 'employer'): ?>

<a href="/victory/employer/dashboard.php">Dashboard</a>
<a href="/victory/employer/post-job.php">Post Job</a>
<a href="/victory/employer/manage-jobs.php">Manage Jobs</a>

<?php endif; ?>

<?php if (isLoggedIn() && $_SESSION['role'] === 'admin'): ?>

<a href="/victory/admin/dashboard.php">Admin</a>

<?php endif; ?>

</nav>

<!-- RIGHT SIDE -->
<div class="header-right">

<?php if (isLoggedIn()): ?>

<!-- Notification -->
<a href="#" class="notify">
<i class="fa fa-bell"></i>
</a>

<!-- PROFILE -->
<div class="profile-box">

<?php
$profile_pic = basename((string) ($_SESSION['profile_pic'] ?? ""));
?>

<?php if($profile_pic != ""): ?>
<img src="/victory/uploads/profile/<?php echo e($profile_pic); ?>">
<?php else: ?>
<i class="fa fa-user-circle profile-icon"></i>
<?php endif; ?>

</div>

<form method="POST" action="/victory/auth/logout.php" class="logout-btn">
<?php echo csrfInput(); ?>
<button type="submit">Logout</button>
</form>

<?php endif; ?>

</div>

</header>

<div class="container">