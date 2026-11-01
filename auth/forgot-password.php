<?php
require_once __DIR__ . "/../includes/session.php";

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost();

    $email = inputString($_POST['email'] ?? "", 100);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expire = date("Y-m-d H:i:s", time() + 3600);

            $update = $conn->prepare("UPDATE users SET reset_token = ?, token_expire = ? WHERE user_id = ?");
            $update->bind_param("ssi", $tokenHash, $expire, $user['user_id']);
            $update->execute();

            $baseUrl = rtrim(getenv('APP_BASE_URL') ?: 'http://localhost/victory', '/');
            $link = $baseUrl . "/auth/reset-password.php?token=" . rawurlencode($token);
            $from = getenv('APP_MAIL_FROM') ?: 'noreply@localhost';
            if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
                $from = 'noreply@localhost';
            }
            $subject = "Victory password reset";
            $body = "Use this link within one hour to reset your Victory password:\r\n\r\n"
                . $link . "\r\n\r\nIf you did not request a reset, ignore this email.";
            $headers = "From: " . $from . "\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n";

            if (!mail($email, $subject, $body, $headers)) {
                error_log("Password reset email delivery failed.");
                $clear = $conn->prepare("UPDATE users SET reset_token = NULL, token_expire = NULL WHERE user_id = ?");
                $clear->bind_param("i", $user['user_id']);
                $clear->execute();
            }
        }
    }

    $message = "If the address is registered and email delivery is configured, password reset instructions will arrive shortly.";
}
?>

<h2>Forgot Password</h2>

<form method="POST">
<?php echo csrfInput(); ?>

<input type="email" name="email" placeholder="Enter Email" required>

<button>Send Reset Link</button>

</form>

<p><?php echo e($message); ?></p>