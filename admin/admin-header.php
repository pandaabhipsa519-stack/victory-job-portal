<?php
require_once "../includes/session.php";
requireAdmin();

require_once "../includes/config.php";
?>

<!DOCTYPE html>
<html>
<head>

<title>Admin Panel - Victory</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

<link rel="stylesheet" href="../assets/css/style.css">

</head>

<body>

<div class="admin-layout">

<!-- SIDEBAR -->
<div class="admin-sidebar">

<h2>Victory</h2>

<a href="dashboard.php">🏠 Dashboard</a>
<a href="manage-students.php">🎓 Students</a>
<a href="manage-employees.php">👔 Professionals</a>
<a href="manage-employers.php">👥 Recruiters</a>
<a href="manage-jobs.php">💼 Jobs</a>
<a href="manage-companies.php">🏢 Companies</a>
<a href="manage-applications.php">📄 Applications</a>
<a href="manage-categories.php">📂 Categories</a>
<a href="../index.php">🌐 View Site</a>
<form method="POST" action="../auth/logout.php">
    <?php echo csrfInput(); ?>
    <button type="submit">🚪 Logout</button>
</form>

</div>

<!-- MAIN CONTENT -->
<div class="admin-content">

<!-- TOP BAR -->
<div class="admin-topbar">

<h3>Admin Dashboard</h3>

<div class="admin-profile">
👤 Admin
</div>

</div>