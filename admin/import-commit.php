<?php
require_once __DIR__ . "/../includes/session.php";
requireAdmin();
requireCsrfPost();
require_once __DIR__ . "/../includes/csv-import.php";

$stage = $_SESSION['phase2_import'] ?? null;
if (!$stage || time() - (int) ($stage['created_at'] ?? 0) > 1800) {
    unset($_SESSION['phase2_import']);
    http_response_code(410);
    exit("Import preview expired. Upload the CSV again.");
}
$type = $stage['type'];
try {
    csvHeadersForType($type);
} catch (InvalidArgumentException $exception) {
    unset($_SESSION['phase2_import']);
    http_response_code(400);
    exit("Invalid import type.");
}

$companyImportLock = false;
if ($type === 'company') {
    $lockResult = $conn->query("SELECT GET_LOCK('victory_phase2_company_management', 10) AS acquired")->fetch_assoc();
    $companyImportLock = isset($lockResult['acquired']) && (int) $lockResult['acquired'] === 1;
    if (!$companyImportLock) {
        http_response_code(503);
        exit("Company imports are busy. Please retry the preview.");
    }
}

$counts = importRowCounts($stage['rows']);
$toInsert = [];
foreach ($stage['rows'] as $item) {
    if ($item['errors']) {
        continue;
    }
    $duplicate = checkImportDuplicate($conn, $type, $item['data']);
    if ($duplicate !== null) {
        if (str_starts_with($duplicate, 'duplicate: ')) {
            $counts['skip']++;
        } else {
            $counts['reject']++;
        }
        continue;
    }
    if ($type === 'employer') {
        $matches = companyMatchesByName($conn, $item['data']['company_name']);
        if (count($matches) !== 1 || $matches[0]['status'] !== 'active') {
            $counts['skip']++;
            continue;
        }
        $item['data']['company_id'] = (int) $matches[0]['company_id'];
    }
    $toInsert[] = $item['data'];
}

$createdAccounts = [];
$inserted = 0;
$conn->begin_transaction();
try {
    foreach ($toInsert as $data) {
        if ($type === 'company') {
            createManagedCompany($conn, $data);
        } else {
            $createdAccounts[] = createManagedAccount($conn, $type, $data);
        }
        $inserted++;
    }
    $conn->commit();
    if ($companyImportLock) {
        releaseCompanyManagementLock($conn);
    }
} catch (Throwable $exception) {
    $conn->rollback();
    if ($companyImportLock) {
        releaseCompanyManagementLock($conn);
    }
    error_log("CSV import transaction failed: " . $exception->getMessage());
    http_response_code(500);
    exit("The import transaction failed. No rows from this commit were saved.");
}

$emailsSent = 0;
$emailsFailed = 0;
foreach ($createdAccounts as $account) {
    if (sendAccountSetupEmail($account['email'], $account['setup_token'])) {
        $emailsSent++;
    } else {
        $emailsFailed++;
    }
}
$counts['insert'] = $inserted;
$_SESSION['phase2_import_summary'] = $counts + ['emails_sent' => $emailsSent, 'emails_failed' => $emailsFailed];
unset($_SESSION['phase2_import']);
header("Location: import-preview.php", true, 303);
exit();
