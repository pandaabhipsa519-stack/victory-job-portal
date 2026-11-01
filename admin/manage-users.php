<?php
require_once __DIR__ . "/../includes/session.php";
requireAdmin();
require_once __DIR__ . "/../includes/account-setup.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost();
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

    $conn->begin_transaction();
    try {
        $expectedStatus = $account['account_status'];
        $update = $conn->prepare("
            UPDATE users SET account_status = ?
            WHERE user_id = ? AND role <> 'admin' AND account_status = ?
        ");
        $update->bind_param("sis", $status, $userId, $expectedStatus);
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
    header("Location: manage-users.php", true, 303);
    exit();
}

$query = inputString($_GET['q'] ?? '', 100);
$where = '';
$values = [];
$types = '';
if ($query !== '') {
    $where = " WHERE email LIKE ? OR CAST(user_id AS CHAR) = ?";
    $values = ['%' . $query . '%', $query];
    $types = 'ss';
}
$stmt = $conn->prepare("SELECT user_id,email,role,account_status FROM users" . $where . " ORDER BY user_id DESC LIMIT 200");
bindStatementParams($stmt, $types, $values);
$stmt->execute();
$users = $stmt->get_result();
include __DIR__ . "/../includes/header.php";
?>
<main class="page-section">
    <div class="page-heading">
        <p class="eyebrow">Admin accounts</p>
        <h1>Manage Users</h1>
    </div>
    <p>Accounts are deactivated instead of deleted so applications, jobs, and saved records remain intact.</p>
    <form method="GET" class="job-search-form">
        <label>Search by email or user ID <input type="search" name="q" value="<?php echo e($query); ?>" maxlength="100"></label>
        <button type="submit">Search</button>
    </form>
    <div class="table-responsive">
        <table class="app-table">
            <thead><tr><th>ID</th><th>Email</th><th>Role</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php while ($user = $users->fetch_assoc()): ?>
                <tr>
                    <td><?php echo (int) $user['user_id']; ?></td>
                    <td><?php echo e($user['email']); ?></td>
                    <td><?php echo e($user['role']); ?></td>
                    <td><?php echo e(str_replace('_', ' ', $user['account_status'])); ?></td>
                    <td>
                        <?php if ($user['role'] === 'admin'): ?>
                            Protected
                        <?php elseif ($user['account_status'] === 'pending_setup'): ?>
                            <form method="POST" action="resend-setup.php">
                                <?php echo csrfInput(); ?>
                                <input type="hidden" name="user_id" value="<?php echo (int) $user['user_id']; ?>">
                                <input type="hidden" name="return_to" value="manage-users.php">
                                <button type="submit">Resend setup</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="manage-users.php">
                                <?php echo csrfInput(); ?>
                                <input type="hidden" name="user_id" value="<?php echo (int) $user['user_id']; ?>">
                                <input type="hidden" name="status" value="<?php echo $user['account_status'] === 'active' ? 'inactive' : 'active'; ?>">
                                <button type="submit"><?php echo $user['account_status'] === 'active' ? 'Deactivate' : 'Reactivate'; ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            <?php if ($users->num_rows === 0): ?><tr><td colspan="5">No users found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<?php include __DIR__ . "/../includes/footer.php"; ?>
