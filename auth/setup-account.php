<?php
require_once __DIR__ . "/../includes/session.php";

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$tokenHash = is_string($token) && preg_match('/\A[a-f0-9]{64}\z/', $token)
    ? hash('sha256', $token)
    : null;
$message = '';
$validAccount = null;

if ($tokenHash !== null) {
    $lookup = $conn->prepare("
        SELECT user_id
        FROM users
        WHERE setup_token_hash = ?
          AND setup_token_expires_at > NOW()
          AND account_status = 'pending_setup'
        LIMIT 1
    ");
    $lookup->bind_param("s", $tokenHash);
    $lookup->execute();
    $validAccount = $lookup->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost();
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';

    if (!$validAccount) {
        $message = "This setup link is invalid, expired, or already used.";
    } elseif (strlen($password) < 12 || strlen($password) > 72) {
        $message = "Use a password between 12 and 72 bytes.";
    } elseif (!hash_equals($password, $confirmation)) {
        $message = "The passwords do not match.";
    } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $conn->begin_transaction();
        try {
            $lock = $conn->prepare("
                SELECT user_id FROM users
                WHERE user_id = ? AND setup_token_hash = ?
                  AND setup_token_expires_at > NOW()
                  AND account_status = 'pending_setup'
                FOR UPDATE
            ");
            $userId = (int) $validAccount['user_id'];
            $lock->bind_param("is", $userId, $tokenHash);
            $lock->execute();
            if (!$lock->get_result()->fetch_assoc()) {
                throw new RuntimeException("The setup link is no longer valid.");
            }

            $update = $conn->prepare("
                UPDATE users
                SET password = ?, account_status = 'active',
                    setup_token_hash = NULL, setup_token_expires_at = NULL
                WHERE user_id = ? AND setup_token_hash = ?
            ");
            $update->bind_param("sis", $passwordHash, $userId, $tokenHash);
            $update->execute();
            if ($update->affected_rows !== 1) {
                throw new RuntimeException("Account setup could not be completed.");
            }
            $conn->commit();
            $validAccount = null;
            $message = "Your password is set. You can now sign in.";
        } catch (Throwable $exception) {
            $conn->rollback();
            if ($exception->getMessage() !== "The setup link is no longer valid.") {
                error_log("Account setup failed: " . $exception->getMessage());
            }
            $message = "This setup link is invalid, expired, or already used.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set up account | Victory</title>
    <link rel="stylesheet" href="../assets/css/design-system.css">
</head>
<body class="auth-page">
<main class="page-section">
    <section class="job-detail-card" aria-labelledby="setup-title">
        <h1 id="setup-title">Set up your account</h1>
        <?php if ($message !== ''): ?><p role="status"><?php echo e($message); ?></p><?php endif; ?>
        <?php if ($validAccount): ?>
            <form method="POST">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="token" value="<?php echo e($token); ?>">
                <label for="setup-password">New password</label>
                <input id="setup-password" type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required>
                <label for="setup-password-confirmation">Confirm password</label>
                <input id="setup-password-confirmation" type="password" name="password_confirmation" minlength="12" maxlength="72" autocomplete="new-password" required>
                <button type="submit">Set password</button>
            </form>
        <?php elseif ($message === ''): ?>
            <p>This setup link is invalid, expired, or already used.</p>
        <?php endif; ?>
        <p><a href="login.php">Return to sign in</a></p>
    </section>
</main>
</body>
</html>
