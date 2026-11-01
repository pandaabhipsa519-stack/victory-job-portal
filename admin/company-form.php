<?php
require_once __DIR__ . "/../includes/session.php";
requireAdmin();
require_once __DIR__ . "/../includes/admin-management.php";

$companyId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$editing = $companyId !== false && $companyId !== null;
$error = '';
$notice = '';
$company = null;
if ($editing) {
    $stmt = $conn->prepare("SELECT * FROM companies WHERE company_id = ? LIMIT 1");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $company = $stmt->get_result()->fetch_assoc();
    if (!$company) {
        http_response_code(404);
        exit("Company not found.");
    }
}

$data = [
    'company_name' => $company['company_name'] ?? '',
    'industry' => $company['industry'] ?? '',
    'website' => $company['website'] ?? '',
    'official_email' => $company['official_email'] ?? '',
    'phone' => $company['phone'] ?? '',
    'location' => $company['location'] ?? '',
    'description' => $company['description'] ?? '',
];
$uploadedLogo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost();
    foreach ($data as $key => $unused) {
        if (isset($_POST[$key]) && is_string($_POST[$key])) {
            $data[$key] = trim($_POST[$key]);
        }
    }
    $errors = validateManagementData('company', $data);
    if ($errors) {
        $error = implode(' ', $errors);
    } else {
        $fileError = '';
        $uploadedLogo = saveUploadedFile(
            'logo',
            __DIR__ . '/../uploads/company-logos',
            ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
            2 * 1024 * 1024,
            $fileError
        );
        if ($uploadedLogo === false) {
            $error = $fileError;
        } else {
            $companyLockAcquired = false;
            $lockResult = $conn->query("SELECT GET_LOCK('victory_phase2_company_management', 10) AS acquired")->fetch_assoc();
            $companyLockAcquired = isset($lockResult['acquired']) && (int) $lockResult['acquired'] === 1;
            if (!$companyLockAcquired) {
                if ($uploadedLogo !== '') {
                    $newLogo = __DIR__ . '/../uploads/company-logos/' . basename($uploadedLogo);
                    if (is_file($newLogo)) {
                        unlink($newLogo);
                    }
                }
                $error = "Company management is busy. Please try again.";
            }
            if ($error === '') {
                $conn->begin_transaction();
                try {
                if ($editing) {
                    $logo = $uploadedLogo !== '' ? $uploadedLogo : $company['logo'];
                    $stmt = $conn->prepare("
                        UPDATE companies
                        SET company_name = ?, industry = ?, website = ?, official_email = ?,
                            phone = ?, location = ?, description = ?, logo = ?
                        WHERE company_id = ?
                    ");
                    $stmt->bind_param(
                        "ssssssssi",
                        $data['company_name'],
                        $data['industry'],
                        $data['website'],
                        $data['official_email'],
                        $data['phone'],
                        $data['location'],
                        $data['description'],
                        $logo,
                        $companyId
                    );
                    $stmt->execute();
                    $sync = $conn->prepare("
                        UPDATE employers
                        SET company_name = ?, industry = ?, location = ?, about = ?, logo = ?
                        WHERE company_id = ?
                    ");
                    $sync->bind_param(
                        "sssssi",
                        $data['company_name'],
                        $data['industry'],
                        $data['location'],
                        $data['description'],
                        $logo,
                        $companyId
                    );
                    $sync->execute();
                    writeAdminAudit($conn, 'update', 'company', (int) $companyId, 'Admin updated company details');
                    $message = 'Company details updated.';
                } else {
                    $companyId = createManagedCompany($conn, $data);
                    if ($uploadedLogo !== '') {
                        $logo = $uploadedLogo;
                        $stmt = $conn->prepare("UPDATE companies SET logo = ? WHERE company_id = ?");
                        $stmt->bind_param("si", $logo, $companyId);
                        $stmt->execute();
                    }
                    $message = 'Company created.';
                }
                $conn->commit();
                if ($companyLockAcquired) {
                    releaseCompanyManagementLock($conn);
                }
                $_SESSION['management_flash'] = $message;
                header("Location: manage-companies.php", true, 303);
                exit();
                } catch (Throwable $exception) {
                    $conn->rollback();
                    if ($companyLockAcquired) {
                        releaseCompanyManagementLock($conn);
                    }
                    if ($uploadedLogo !== '') {
                        $newLogo = __DIR__ . '/../uploads/company-logos/' . basename($uploadedLogo);
                        if (is_file($newLogo)) {
                            unlink($newLogo);
                        }
                    }
                    error_log("Admin company management failed: " . $exception->getMessage());
                    $error = "The company could not be saved. Check the submitted details and try again.";
                }
            }
        }
    }
}

include __DIR__ . "/../includes/header.php";
?>
<main class="page-section">
    <div class="page-heading">
        <p class="eyebrow">Admin organization management</p>
        <h1><?php echo $editing ? 'Edit' : 'Add'; ?> company</h1>
    </div>
    <?php if ($error !== ''): ?><p class="form-error" role="alert"><?php echo e($error); ?></p><?php endif; ?>
    <form method="POST" enctype="multipart/form-data" class="job-detail-card">
        <?php echo csrfInput(); ?>
        <div class="form-grid">
            <label>Company name
                <input type="text" name="company_name" maxlength="150" required value="<?php echo e($data['company_name']); ?>">
            </label>
            <label>Industry
                <input type="text" name="industry" maxlength="100" value="<?php echo e($data['industry']); ?>">
            </label>
            <label>Website
                <input type="url" name="website" maxlength="255" value="<?php echo e($data['website']); ?>">
            </label>
            <label>Official email
                <input type="email" name="official_email" maxlength="100" value="<?php echo e($data['official_email']); ?>">
            </label>
            <label>Phone
                <input type="tel" name="phone" maxlength="20" required value="<?php echo e($data['phone']); ?>">
            </label>
            <label>Location
                <input type="text" name="location" maxlength="100" value="<?php echo e($data['location']); ?>">
            </label>
            <label>Description
                <textarea name="description" maxlength="10000"><?php echo e($data['description']); ?></textarea>
            </label>
            <label>Logo (optional, JPEG/PNG/WebP, max 2 MB)
                <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp">
            </label>
        </div>
        <button type="submit">Save company</button>
        <a href="manage-companies.php">Cancel</a>
    </form>
</main>
<?php include __DIR__ . "/../includes/footer.php"; ?>
