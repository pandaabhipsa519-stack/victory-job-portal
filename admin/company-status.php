<?php
require_once __DIR__ . "/../includes/session.php";
requireAdmin();
requireCsrfPost();
require_once __DIR__ . "/../includes/account-setup.php";

$companyId = filter_var($_POST['company_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$status = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
if ($companyId === false || $companyId === null || !in_array($status, ['active', 'inactive'], true)) {
    http_response_code(400);
    exit("Invalid company status request.");
}
$conn->begin_transaction();
try {
    $stmt = $conn->prepare("UPDATE companies SET status = ? WHERE company_id = ? AND status <> ?");
    $stmt->bind_param("sis", $status, $companyId, $status);
    $stmt->execute();
    if ($stmt->affected_rows !== 1) {
        throw new RuntimeException("Company was not found or already has that status.");
    }
    writeAdminAudit($conn, $status === 'inactive' ? 'deactivate' : 'reactivate', 'company', (int) $companyId, 'Admin changed company status', ['status' => $status]);
    $conn->commit();
} catch (Throwable $exception) {
    $conn->rollback();
    error_log("Company status update failed: " . $exception->getMessage());
    http_response_code(409);
    exit("The company status could not be changed.");
}
header("Location: manage-companies.php", true, 303);
exit();
