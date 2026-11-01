<?php
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function inputString($value, $maxLength = 10000)
{
    if (!is_string($value)) {
        return "";
    }

    $value = trim($value);
    return strlen($value) <= $maxLength ? $value : "";
}

function clean($value)
{
    return e(inputString($value));
}

function csrfToken()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new RuntimeException('A session must be active before generating a CSRF token.');
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfInput()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrfToken($token)
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrfPost()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Method not allowed.');
    }

    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($token)) {
        http_response_code(403);
        exit('Your session expired or the request could not be verified. Please go back, refresh, and try again.');
    }
}

function bindStatementParams($statement, $types, array &$values)
{
    if ($types === "") {
        return;
    }

    $references = [$types];
    foreach ($values as $key => &$value) {
        $references[] = &$value;
    }
    call_user_func_array([$statement, 'bind_param'], $references);
}

function saveUploadedFile($field, $directory, array $allowedMimeTypes, $maxSize, &$error)
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return "";
    }

    $file = $_FILES[$field];
    if (!isset($file['error'], $file['tmp_name'], $file['size'], $file['name'])
        || !is_int($file['error']) || !is_int($file['size'])
        || !is_string($file['tmp_name']) || !is_string($file['name'])
        || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        $error = "The selected file could not be uploaded.";
        return false;
    }

    if ($file['size'] <= 0 || $file['size'] > $maxSize) {
        $error = "The selected file is empty or exceeds the size limit.";
        return false;
    }

    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = $allowedMimeTypes;
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isset($extensions[$mimeType])
        || ($extensions[$mimeType] === "jpg" && !in_array($extension, ["jpg", "jpeg"], true))
        || ($extensions[$mimeType] !== "jpg" && $extension !== $extensions[$mimeType])) {
        $error = "The selected file type is not allowed.";
        return false;
    }

    $directory = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!is_dir($directory) || !is_writable($directory)) {
        error_log("Upload destination is unavailable: " . $directory);
        $error = "File uploads are temporarily unavailable.";
        return false;
    }

    $fileName = bin2hex(random_bytes(16)) . "." . $extensions[$mimeType];
    if (!move_uploaded_file($file['tmp_name'], $directory . $fileName)) {
        $error = "The selected file could not be saved.";
        return false;
    }

    return $fileName;
}

function saveJobForUser($conn, $userId, $jobId)
{
    $conn->begin_transaction();
    try {
        $job = $conn->prepare("
            SELECT jobs.job_id
            FROM jobs
            JOIN employers ON employers.emp_id = jobs.emp_id
            JOIN users ON users.user_id = employers.user_id
            LEFT JOIN companies ON companies.company_id = employers.company_id
            WHERE jobs.job_id = ?
              AND users.account_status = 'active'
              AND (employers.company_id IS NULL OR companies.status = 'active')
            FOR UPDATE
        ");
        $job->bind_param("i", $jobId);
        $job->execute();
        if (!$job->get_result()->fetch_assoc()) {
            $conn->rollback();
            return 'not_found';
        }

        $existing = $conn->prepare("SELECT id FROM saved_jobs WHERE user_id = ? AND job_id = ? LIMIT 1");
        $existing->bind_param("ii", $userId, $jobId);
        $existing->execute();
        if ($existing->get_result()->fetch_assoc()) {
            $conn->commit();
            return 'already_saved';
        }

        $insert = $conn->prepare("INSERT INTO saved_jobs (user_id, job_id) VALUES (?, ?)");
        $insert->bind_param("ii", $userId, $jobId);
        $insert->execute();
        $conn->commit();
        return 'saved';
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }
}

/* =========================
   REDIRECT FUNCTION
========================= */
function redirect($url){
    header("Location: $url");
    exit();
}

/* =========================
   ALERT MESSAGE
========================= */
function alert($msg){
    echo "<script>alert(" . json_encode(
        (string) $msg,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    ) . ");</script>";
}

/* =========================
   GET USER DATA
========================= */
function getUser($conn, $user_id){
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id=?");
    $stmt->bind_param("i",$user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/* =========================
   COUNT RECORDS
========================= */
function countData($conn, $table){
    $allowedTables = [
        'users', 'employers', 'job_seekers', 'jobs', 'applications',
        'saved_jobs', 'notifications', 'categories', 'contact_messages',
    ];
    if (!in_array($table, $allowedTables, true)) {
        throw new InvalidArgumentException('Unsupported count table.');
    }

    $result = $conn->query("SELECT COUNT(*) as total FROM `" . $table . "`");
    return $result->fetch_assoc()['total'];
}

?>