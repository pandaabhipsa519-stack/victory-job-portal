
<footer>
    <p>© <?php echo date("Y"); ?> Victory Job Portal. All rights reserved.</p>
</footer>

<!-- SETTINGS PANEL -->
<aside id="settingsPanel" class="settings-panel" aria-label="Site settings" style="
position:fixed;
right:-260px;
top:0;
width:260px;
height:100%;
background:#ffffff;
box-shadow:-3px 0 10px rgba(0,0,0,0.2);
padding:20px;
transition:0.3s;
z-index:9999;
overflow:auto;
">

<h3>Settings <button class="settings-close" type="button" onclick="toggleSettings()" aria-label="Close settings">×</button></h3>
<hr>

<a href="/victory/index.php">🏠 Home</a><br><br>

<?php if (isset($_SESSION['role'])): ?>
    <?php if ($_SESSION['role'] === 'jobseeker'): ?>
        <a href="/victory/jobseeker/profile.php">👤 Profile</a><br><br>
    <?php elseif ($_SESSION['role'] === 'employer'): ?>
        <a href="/victory/employer/dashboard.php">👤 Employer dashboard</a><br><br>
    <?php elseif ($_SESSION['role'] === 'admin'): ?>
        <a href="/victory/admin/dashboard.php">👤 Admin dashboard</a><br><br>
    <?php endif; ?>
<?php endif; ?>

<?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
    <a href="/victory/admin/dashboard.php">👑 Admin Panel</a><br><br>
    <a href="/victory/admin/manage-users.php">🔐 Manage Permissions</a><br><br>
<?php endif; ?>

<label for="settings-language"><b>🌐 Language</b></label><br>
<select id="settings-language">
    <option>English</option>
    <option>Hindi</option>
    <option>Odia</option>
</select>
<br><br>

<a href="/victory/about.php">ℹ About</a><br><br>

<button onclick="toggleDarkMode()">🌙 Dark Mode</button>

<?php if (isset($_SESSION['user_id'])): ?>
<form method="POST" action="/victory/auth/logout.php">
    <?php echo csrfInput(); ?>
    <button type="submit">Log out</button>
</form>
<?php endif; ?>

</aside>

<script src="/victory/assets/js/main.js?v=20261031"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</body>
</html>