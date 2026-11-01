<?php
require_once __DIR__ . "/../includes/session.php";

$token = $_GET['token'] ?? $_POST['token'] ?? "";
$message = "";
$userId = null;
$tokenHash = null;

if (is_string($token) && preg_match('/\A[a-f0-9]{64}\z/', $token)) {
    $tokenHash = hash('sha256', $token);
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE reset_token = ? AND token_expire > NOW() LIMIT 1");
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $userId = $user ? (int) $user['user_id'] : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost();
    $newPassword = is_string($_POST['password'] ?? null) ? $_POST['password'] : "";
    if ($userId === null) {
        $message = "This reset link is invalid or has expired.";
    } elseif (strlen($newPassword) < 12 || strlen($newPassword) > 4096) {
        $message = "Use a password with at least 12 characters.";
    } else {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, token_expire = NULL WHERE user_id = ? AND reset_token = ?");
        $update->bind_param("sis", $passwordHash, $userId, $tokenHash);
        $update->execute();
        if ($update->affected_rows === 1) {
            $message = "Password updated. You can now sign in.";
            $userId = null;
        } else {
            $message = "This reset link is invalid or has expired.";
            $userId = null;
        }
    }
}
?>

<h2>Reset Password</h2>
<?php if ($message !== ""): ?><p role="status"><?php echo e($message); ?></p><?php endif; ?>

<?php if ($userId !== null): ?>
<form method="POST">
<?php echo csrfInput(); ?>
<input type="hidden" name="token" value="<?php echo e($token); ?>">
<input type="password" name="password" placeholder="New Password" minlength="12" required>
<button>Update Password</button>
</form>
<?php endif; ?>
