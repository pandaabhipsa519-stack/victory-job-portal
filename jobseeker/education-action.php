<?php
require_once __DIR__ . "/../includes/session.php";

requireJobseeker();
requireCsrfPost();

$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
if (!is_string($action)) {
    header("Location: profile.php?education_status=invalid", true, 303);
    exit();
}

try {
    $ownerStmt = $conn->prepare("
        SELECT js_id, profile_type
        FROM job_seekers
        WHERE user_id = ?
        LIMIT 1
    ");
    $ownerStmt->bind_param("i", $userId);
    $ownerStmt->execute();
    $owner = $ownerStmt->get_result()->fetch_assoc();

    if (!$owner || !in_array($owner['profile_type'], ['student', 'unclassified'], true)) {
        http_response_code(403);
        exit("Student education records are unavailable.");
    }

    $jsId = (int) $owner['js_id'];

    if ($action === 'delete') {
        $educationIdValue = $_POST['education_id'] ?? '';
        if (!is_string($educationIdValue) || !ctype_digit($educationIdValue) || (int) $educationIdValue < 1) {
            header("Location: profile.php?education_status=invalid", true, 303);
            exit();
        }

        $educationId = (int) $educationIdValue;
        $deleteStmt = $conn->prepare("
            DELETE FROM student_educations
            WHERE education_id = ? AND js_id = ?
        ");
        $deleteStmt->bind_param("ii", $educationId, $jsId);
        $deleteStmt->execute();

        header(
            "Location: profile.php?education_status=" . ($deleteStmt->affected_rows === 1 ? 'deleted' : 'not_found'),
            true,
            303
        );
        exit();
    }

    if (!in_array($action, ['add', 'edit'], true)) {
        header("Location: profile.php?education_status=invalid", true, 303);
        exit();
    }

    $fields = [
        'qualification',
        'college_university',
        'branch',
        'admission_year',
        'passing_year',
        'current_year_semester',
        'grade_type',
        'grade_value',
    ];
    foreach ($fields as $field) {
        if (!isset($_POST[$field]) || !is_string($_POST[$field]) || !mb_check_encoding($_POST[$field], 'UTF-8')) {
            header("Location: profile.php?education_status=invalid", true, 303);
            exit();
        }
    }

    $maxLengths = [
        'qualification' => 150,
        'college_university' => 180,
        'branch' => 150,
        'current_year_semester' => 50,
    ];
    $values = [];
    foreach ($maxLengths as $field => $maxLength) {
        $values[$field] = trim($_POST[$field]);
        if (mb_strlen($values[$field], 'UTF-8') > $maxLength
            || preg_match('/[\x00-\x1F\x7F]/u', $values[$field]) === 1) {
            header("Location: profile.php?education_status=invalid", true, 303);
            exit();
        }
    }

    $currentYear = (int) date('Y');
    $admissionYear = filter_var($_POST['admission_year'], FILTER_VALIDATE_INT);
    $passingYear = filter_var($_POST['passing_year'], FILTER_VALIDATE_INT);
    if ($values['qualification'] === ''
        || $values['college_university'] === ''
        || $values['branch'] === ''
        || $values['current_year_semester'] === ''
        || $admissionYear === false
        || $passingYear === false
        || $admissionYear < 1950
        || $passingYear < 1950
        || $admissionYear > $currentYear + 10
        || $passingYear > $currentYear + 10
        || $admissionYear > $passingYear) {
        header("Location: profile.php?education_status=invalid", true, 303);
        exit();
    }

    $gradeType = trim($_POST['grade_type']);
    $gradeInput = trim($_POST['grade_value']);
    $cgpa = null;
    $percentage = null;
    if ($gradeInput === ''
        || !in_array($gradeType, ['cgpa', 'percentage'], true)
        || preg_match('/\A\d{1,3}(?:\.\d{1,2})?\z/', $gradeInput) !== 1) {
        header("Location: profile.php?education_status=invalid", true, 303);
        exit();
    }

    $gradeValue = (float) $gradeInput;
    if ($gradeType === 'cgpa' && $gradeValue <= 10) {
        $cgpa = $gradeValue;
    } elseif ($gradeType === 'percentage' && $gradeValue <= 100) {
        $percentage = $gradeValue;
    } else {
        header("Location: profile.php?education_status=invalid", true, 303);
        exit();
    }

    if ($action === 'add') {
        $saveStmt = $conn->prepare("
            INSERT INTO student_educations
                (js_id, qualification, college_university, branch, admission_year,
                 passing_year, current_year_semester, cgpa, percentage)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $saveStmt->bind_param(
            "isssissdd",
            $jsId,
            $values['qualification'],
            $values['college_university'],
            $values['branch'],
            $admissionYear,
            $passingYear,
            $values['current_year_semester'],
            $cgpa,
            $percentage
        );
    } else {
        $educationIdValue = $_POST['education_id'] ?? '';
        if (!is_string($educationIdValue) || !ctype_digit($educationIdValue) || (int) $educationIdValue < 1) {
            header("Location: profile.php?education_status=invalid", true, 303);
            exit();
        }

        $educationId = (int) $educationIdValue;
        $saveStmt = $conn->prepare("
            UPDATE student_educations
            SET qualification = ?, college_university = ?, branch = ?,
                admission_year = ?, passing_year = ?, current_year_semester = ?,
                cgpa = ?, percentage = ?
            WHERE education_id = ? AND js_id = ?
        ");
        $saveStmt->bind_param(
            "sssiisddii",
            $values['qualification'],
            $values['college_university'],
            $values['branch'],
            $admissionYear,
            $passingYear,
            $values['current_year_semester'],
            $cgpa,
            $percentage,
            $educationId,
            $jsId
        );
    }

    $saveStmt->execute();
    if ($action === 'edit' && $saveStmt->affected_rows === 0) {
        $existsStmt = $conn->prepare("
            SELECT education_id
            FROM student_educations
            WHERE education_id = ? AND js_id = ?
            LIMIT 1
        ");
        $existsStmt->bind_param("ii", $educationId, $jsId);
        $existsStmt->execute();
        if (!$existsStmt->get_result()->fetch_assoc()) {
            header("Location: profile.php?education_status=not_found", true, 303);
            exit();
        }
    }

    header("Location: profile.php?education_status=saved", true, 303);
    exit();
} catch (mysqli_sql_exception $exception) {
    error_log("Student education update failed: " . $exception->getMessage());
    header("Location: profile.php?education_status=save", true, 303);
    exit();
}
