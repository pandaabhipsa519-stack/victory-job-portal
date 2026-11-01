<?php
require_once __DIR__ . "/../includes/session.php";
requireAdmin();
requireCsrfPost();
require_once __DIR__ . "/../includes/account-setup.php";

$userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$status = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
if ($userId === false || $userId === null || !in_array($status, ['active', 'inactive'], true)) {
    http_response_code(400);
    exit("Invalid account status request.");
}
$stmt = $conn->prepare("SELECT role, account_status FROM users WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();
if (!$account || $account['role'] === 'admin'
    || ($status === 'active' && $account['account_status'] !== 'inactive')
    || ($status === 'inactive' && $account['account_status'] !== 'active')) {
    http_response_code(409);
    exit("This account cannot be changed through this action.");
}
$expectedStatus = $account['account_status'];
$update = $conn->prepare("
    UPDATE users SET account_status = ?
    WHERE user_id = ? AND role <> 'admin' AND account_status = ?
");
$update->bind_param("sis", $status, $userId, $expectedStatus);
$conn->begin_transaction();
try {
    $update->execute();
    if ($update->affected_rows !== 1) {
        throw new RuntimeException("Account status was not changed.");
    }
    writeAdminAudit($conn, $status === 'inactive' ? 'deactivate' : 'reactivate', 'user', (int) $userId, 'Admin changed account status', ['status' => $status]);
    $conn->commit();
} catch (Throwable $exception) {
    $conn->rollback();
    error_log("Account status update failed: " . $exception->getMessage());
    http_response_code(500);
    exit("The account status could not be changed.");
}
$returnTo = $_POST['return_to'] ?? 'manage-users.php';
$allowedReturns = ['manage-users.php', 'manage-students.php', 'manage-employees.php', 'manage-employers.php'];
if (!is_string($returnTo) || !in_array($returnTo, $allowedReturns, true)) {
    $returnTo = 'manage-users.php';
}
header("Location: " . $returnTo, true, 303);
exit();
