<?php
require_once __DIR__ . "/../includes/session.php";
requireAdmin();
requireCsrfPost();
require_once __DIR__ . "/../includes/account-setup.php";

$userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($userId === false || $userId === null) {
    http_response_code(400);
    exit("Invalid account.");
}
$stmt = $conn->prepare("SELECT email FROM users WHERE user_id = ? AND account_status = 'pending_setup' AND role <> 'admin' LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();
if (!$account) {
    http_response_code(404);
    exit("Pending account not found.");
}
$conn->begin_transaction();
try {
    $token = createAccountSetupToken($conn, (int) $userId);
    writeAdminAudit($conn, 'resend_setup', 'user', (int) $userId, 'Admin requested a new setup link');
    $conn->commit();
    $sent = sendAccountSetupEmail($account['email'], $token);
    $_SESSION['management_flash'] = $sent
        ? 'A new password setup link was sent.'
        : 'A new setup token was issued, but email delivery failed.';
} catch (Throwable $exception) {
    $conn->rollback();
    error_log("Setup-link resend failed: " . $exception->getMessage());
    http_response_code(500);
    exit("A setup link could not be issued.");
}
$returnTo = $_POST['return_to'] ?? 'manage-users.php';
$allowedReturns = ['manage-users.php', 'manage-students.php', 'manage-employees.php', 'manage-employers.php'];
if (!is_string($returnTo) || !in_array($returnTo, $allowedReturns, true)) {
    $returnTo = 'manage-users.php';
}
header("Location: " . $returnTo, true, 303);
exit();
