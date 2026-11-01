<?php
require_once __DIR__ . "/../includes/session.php";

requireJobseeker();
requireCsrfPost();

$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
if (!is_string($action) || !in_array($action, ['add', 'edit', 'delete'], true)) {
    header("Location: profile.php?skill_status=invalid#skills-section", true, 303);
    exit();
}

try {
    $ownerStmt = $conn->prepare("
        SELECT js_id
        FROM job_seekers
        WHERE user_id = ?
        LIMIT 1
    ");
    $ownerStmt->bind_param("i", $userId);
    $ownerStmt->execute();
    $owner = $ownerStmt->get_result()->fetch_assoc();

    if (!$owner) {
        http_response_code(403);
        exit("Job-seeker profile is unavailable.");
    }

    $jsId = (int) $owner['js_id'];
    $skillIdValue = $_POST['skill_id'] ?? '';
    $skillId = null;
    if ($action !== 'add') {
        if (!is_string($skillIdValue) || !ctype_digit($skillIdValue) || (int) $skillIdValue < 1) {
            header("Location: profile.php?skill_status=invalid#skills-section", true, 303);
            exit();
        }
        $skillId = (int) $skillIdValue;

        $ownedStmt = $conn->prepare("
            SELECT skill_id
            FROM student_skills
            WHERE skill_id = ? AND js_id = ?
            LIMIT 1
        ");
        $ownedStmt->bind_param("ii", $skillId, $jsId);
        $ownedStmt->execute();
        if (!$ownedStmt->get_result()->fetch_assoc()) {
            header("Location: profile.php?skill_status=not_found#skills-section", true, 303);
            exit();
        }
    }

    if ($action === 'delete') {
        $deleteStmt = $conn->prepare("
            DELETE FROM student_skills
            WHERE skill_id = ? AND js_id = ?
        ");
        $deleteStmt->bind_param("ii", $skillId, $jsId);
        $deleteStmt->execute();
        $deleteStatus = $deleteStmt->affected_rows === 1 ? 'deleted' : 'not_found';
        header("Location: profile.php?skill_status=" . $deleteStatus . "#skills-section", true, 303);
        exit();
    }

    if (!isset($_POST['skill_name'], $_POST['proficiency'])
        || !is_string($_POST['skill_name'])
        || !is_string($_POST['proficiency'])
        || !mb_check_encoding($_POST['skill_name'], 'UTF-8')
        || !mb_check_encoding($_POST['proficiency'], 'UTF-8')) {
        header("Location: profile.php?skill_status=invalid#skills-section", true, 303);
        exit();
    }

    $skillName = preg_replace('/\A[\p{Z}\s]+|[\p{Z}\s]+\z/u', '', $_POST['skill_name']);
    $proficiency = trim($_POST['proficiency']);
    if (!is_string($skillName)
        || $skillName === ''
        || mb_strlen($skillName, 'UTF-8') > 191
        || preg_match('/\p{Cc}/u', $skillName) === 1
        || !in_array($proficiency, ['Beginner', 'Intermediate', 'Advanced'], true)) {
        header("Location: profile.php?skill_status=invalid#skills-section", true, 303);
        exit();
    }

    $skillNameKey = mb_strtolower($skillName, 'UTF-8');
    if ($action === 'edit') {
        $duplicateStmt = $conn->prepare("
            SELECT skill_id
            FROM student_skills
            WHERE js_id = ? AND skill_name_key = ? AND skill_id <> ?
            LIMIT 1
        ");
        $duplicateStmt->bind_param("isi", $jsId, $skillNameKey, $skillId);
    } else {
        $duplicateStmt = $conn->prepare("
            SELECT skill_id
            FROM student_skills
            WHERE js_id = ? AND skill_name_key = ?
            LIMIT 1
        ");
        $duplicateStmt->bind_param("is", $jsId, $skillNameKey);
    }
    $duplicateStmt->execute();
    if ($duplicateStmt->get_result()->fetch_assoc()) {
        header("Location: profile.php?skill_status=duplicate#skills-section", true, 303);
        exit();
    }

    if ($action === 'add') {
        $saveStmt = $conn->prepare("
            INSERT INTO student_skills (js_id, skill_name, skill_name_key, proficiency)
            VALUES (?, ?, ?, ?)
        ");
        $saveStmt->bind_param("isss", $jsId, $skillName, $skillNameKey, $proficiency);
    } else {
        $saveStmt = $conn->prepare("
            UPDATE student_skills
            SET skill_name = ?, skill_name_key = ?, proficiency = ?
            WHERE skill_id = ? AND js_id = ?
        ");
        $saveStmt->bind_param("sssii", $skillName, $skillNameKey, $proficiency, $skillId, $jsId);
    }
    $saveStmt->execute();

    header("Location: profile.php?skill_status=saved#skills-section", true, 303);
    exit();
} catch (mysqli_sql_exception $exception) {
    if ((int) $exception->getCode() === 1062) {
        header("Location: profile.php?skill_status=duplicate#skills-section", true, 303);
        exit();
    }
    error_log("Job-seeker skill update failed: " . $exception->getMessage());
    header("Location: profile.php?skill_status=save#skills-section", true, 303);
    exit();
}
