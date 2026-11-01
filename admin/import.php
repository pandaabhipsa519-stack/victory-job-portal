<?php
require_once __DIR__ . "/../includes/session.php";
requireAdmin();
require_once __DIR__ . "/../includes/csv-import.php";

$type = is_string($_GET['type'] ?? $_POST['type'] ?? null) ? ($_GET['type'] ?? $_POST['type']) : '';
try {
    $headers = csvHeadersForType($type);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    exit("Invalid import type.");
}
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost();
    $file = $_FILES['csv'] ?? null;
    if (!is_array($file) || !isset($file['error'], $file['tmp_name'], $file['size'], $file['name'])
        || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])
        || !is_int($file['size']) || $file['size'] < 1 || $file['size'] > 2 * 1024 * 1024
        || strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $error = "Upload a CSV file no larger than 2 MB.";
    } else {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($mime, ['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/csv'], true)) {
            $error = "The uploaded file is not a supported CSV.";
        } else {
            $handle = fopen($file['tmp_name'], 'rb');
            if ($handle === false) {
                $error = "The CSV file could not be read.";
            } else {
                $inputHeaders = fgetcsv($handle, 8192, ',', '"', '\\');
                if (!is_array($inputHeaders)) {
                    $error = "The CSV file is empty.";
                } else {
                    $inputHeaders[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $inputHeaders[0]);
                    $inputHeaders = array_map(static function ($value) {
                        return strtolower(trim((string) $value));
                    }, $inputHeaders);
                    if ($inputHeaders !== $headers) {
                        $error = "CSV headers do not match the selected template.";
                    }
                }

                $rows = [];
                $line = 1;
                while ($error === '' && ($values = fgetcsv($handle, 8192, ',', '"', '\\')) !== false) {
                    $line++;
                    if (count($values) === 1 && trim((string) $values[0]) === '') {
                        continue;
                    }
                    if (count($rows) >= 1000) {
                        $error = "CSV files are limited to 1,000 data rows.";
                        break;
                    }
                    $rowErrors = [];
                    if (count($values) !== count($headers)) {
                        $rowErrors[] = "Expected " . count($headers) . " columns but found " . count($values) . ".";
                    }
                    $values = array_slice(array_pad($values, count($headers), ''), 0, count($headers));
                    $data = array_combine($headers, array_map(static function ($value) {
                        return is_string($value) ? trim($value) : '';
                    }, $values));
                    foreach ($data as $field => $value) {
                        if (strlen($value) > 5000) {
                            $rowErrors[] = "A field exceeds the maximum length.";
                        }
                        if (!preg_match('//u', $value)) {
                            $rowErrors[] = "CSV text must use valid UTF-8 encoding.";
                        }
                    }
                    if (rowIsFormulaLike($data)) {
                        $rowErrors[] = "Formula-like cell content is not accepted.";
                    }
                    $rowErrors = array_merge($rowErrors, validateManagementData($type, $data));
                    $rows[] = ['row' => $line, 'data' => $data, 'errors' => array_values(array_unique($rowErrors))];
                }
                fclose($handle);

                if ($error === '' && !$rows) {
                    $error = "The CSV contains no data rows.";
                }
                if ($error === '') {
                    $_SESSION['phase2_import'] = [
                        'type' => $type,
                        'created_at' => time(),
                        'rows' => prepareImportRows($conn, $type, $rows),
                    ];
                    header("Location: import-preview.php", true, 303);
                    exit();
                }
            }
        }
    }
}

include __DIR__ . "/../includes/header.php";
?>
<main class="page-section">
    <div class="page-heading">
        <p class="eyebrow">Admin data import</p>
        <h1>Import <?php echo e(ucfirst($type)); ?> records</h1>
    </div>
    <?php if ($error !== ''): ?><p class="form-error" role="alert"><?php echo e($error); ?></p><?php endif; ?>
    <p>Download the exact CSV header template, fill it with records, and preview all validation results before committing. Existing accounts are never overwritten.</p>
    <p><a href="import-template.php?type=<?php echo e($type); ?>">Download CSV template</a></p>
    <form method="POST" enctype="multipart/form-data" class="job-detail-card">
        <?php echo csrfInput(); ?>
        <input type="hidden" name="type" value="<?php echo e($type); ?>">
        <label for="csv-file">CSV file (maximum 2 MB, up to 1,000 records)</label>
        <input id="csv-file" type="file" name="csv" accept=".csv,text/csv" required>
        <button type="submit">Upload and validate</button>
    </form>
</main>
<?php include __DIR__ . "/../includes/footer.php"; ?>
