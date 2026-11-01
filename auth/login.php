<?php
require_once __DIR__ . "/../includes/session.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    requireCsrfPost();

    $email = inputString($_POST['email'] ?? "", 100);
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : "";

    if (filter_var($email, FILTER_VALIDATE_EMAIL) && $password !== "" && strlen($password) <= 4096) {
        $stmt = $conn->prepare("SELECT user_id,password,role,profile_pic,account_status FROM users WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && $user['account_status'] === 'active' && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            unset($_SESSION['csrf_token']);
            $_SESSION['user_id'] = (int) $user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['profile_pic'] = $user['profile_pic'] ?? '';

            $destinations = [
                'jobseeker' => '../jobseeker/dashboard.php',
                'employer' => '../employer/dashboard.php',
                'admin' => '../admin/dashboard.php',
            ];
            if (isset($destinations[$user['role']])) {
                header("Location: " . $destinations[$user['role']]);
                exit();
            }

            session_unset();
            session_destroy();
        }
    }

    $error = "Email or password is incorrect.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login | Victory</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/login.css">
<link rel="stylesheet" href="../assets/css/design-system.css?v=20261031">

</head>

<body class="auth-page auth-login">

<main class="login-box">

<h1>Welcome back</h1>
<p class="auth-intro">Sign in to continue to your Victory account.</p>
<?php if ($error !== ""): ?>
<p class="form-error" role="alert"><?php echo e($error); ?></p>
<?php endif; ?>

<form method="POST" class="auth-form">
<?php echo csrfInput(); ?>

<div class="input-box">
<i class="fa fa-envelope" aria-hidden="true"></i>
<label class="sr-only" for="login-email">Email address</label>
<input id="login-email" type="email" name="email" placeholder="Email Address" autocomplete="email" required>
</div>

<div class="input-box">
<i class="fa fa-lock" aria-hidden="true"></i>
<label class="sr-only" for="login-password">Password</label>
<input id="login-password" type="password" name="password" placeholder="Password" autocomplete="current-password" required>
</div>

<button class="login-btn" type="submit">Sign in</button>

<p class="links">

<a href="forgot-password.php">Forgot Password?</a>

</p>

<p class="links">

New user?
<a href="register.php">Create Account</a>

</p>

</form>

<a class="back-link" href="/victory/index.php">Back to home</a>
</main>

</body>
</html>
