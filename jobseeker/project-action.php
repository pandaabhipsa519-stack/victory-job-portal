<?php
require_once __DIR__ . "/../includes/session.php";

requireJobseeker();
requireCsrfPost();

$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
if (!is_string($action) || !in_array($action, ['add', 'edit', 'delete'], true)) {
    header("Location: profile.php?project_status=invalid#projects-section", true, 303);
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
    $projectId = null;
    if ($action !== 'add') {
        $projectIdValue = $_POST['project_id'] ?? '';
        if (!is_string($projectIdValue) || !ctype_digit($projectIdValue) || (int) $projectIdValue < 1) {
            header("Location: profile.php?project_status=invalid#projects-section", true, 303);
            exit();
        }

        $projectId = (int) $projectIdValue;
        $ownedStmt = $conn->prepare("
            SELECT project_id
            FROM job_seeker_projects
            WHERE project_id = ? AND js_id = ?
            LIMIT 1
        ");
        $ownedStmt->bind_param("ii", $projectId, $jsId);
        $ownedStmt->execute();
        if (!$ownedStmt->get_result()->fetch_assoc()) {
            header("Location: profile.php?project_status=not_found#projects-section", true, 303);
            exit();
        }
    }

    if ($action === 'delete') {
        $deleteStmt = $conn->prepare("
            DELETE FROM job_seeker_projects
            WHERE project_id = ? AND js_id = ?
        ");
        $deleteStmt->bind_param("ii", $projectId, $jsId);
        $deleteStmt->execute();
        $status = $deleteStmt->affected_rows === 1 ? 'deleted' : 'not_found';
        header("Location: profile.php?project_status=" . $status . "#projects-section", true, 303);
        exit();
    }

    $postedFields = ['title', 'description', 'technologies', 'github_url', 'live_demo_url'];
    $limits = [
        'title' => 150,
        'description' => 10000,
        'technologies' => 2000,
        'github_url' => 2048,
        'live_demo_url' => 2048,
    ];
    $values = [];
    foreach ($postedFields as $field) {
        if (!isset($_POST[$field]) || !is_string($_POST[$field]) || !mb_check_encoding($_POST[$field], 'UTF-8')) {
            header("Location: profile.php?project_status=invalid#projects-section", true, 303);
            exit();
        }
        $values[$field] = trim($_POST[$field]);
        if (mb_strlen($values[$field], 'UTF-8') > $limits[$field]) {
            header("Location: profile.php?project_status=invalid#projects-section", true, 303);
            exit();
        }
    }

    foreach (['title', 'description', 'technologies'] as $requiredField) {
        if ($values[$requiredField] === '') {
            header("Location: profile.php?project_status=invalid#projects-section", true, 303);
            exit();
        }
    }

    foreach (['title', 'description', 'technologies'] as $field) {
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $values[$field]) === 1) {
            header("Location: profile.php?project_status=invalid#projects-section", true, 303);
            exit();
        }
    }

    foreach (['github_url', 'live_demo_url'] as $field) {
        if ($values[$field] === '') {
            $values[$field] = null;
            continue;
        }

        $url = $values[$field];
        $parts = parse_url($url);
        if (preg_match('/[\x00-\x20\x7F]/', $url) === 1
            || filter_var($url, FILTER_VALIDATE_URL) === false
            || !is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || $parts['host'] === ''
            || isset($parts['user'])
            || isset($parts['pass'])) {
            header("Location: profile.php?project_status=invalid#projects-section", true, 303);
            exit();
        }
    }

    if ($action === 'add') {
        $saveStmt = $conn->prepare("
            INSERT INTO job_seeker_projects
                (js_id, title, description, technologies, github_url, live_demo_url)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $saveStmt->bind_param(
            "isssss",
            $jsId,
            $values['title'],
            $values['description'],
            $values['technologies'],
            $values['github_url'],
            $values['live_demo_url']
        );
    } else {
        $saveStmt = $conn->prepare("
            UPDATE job_seeker_projects
            SET title = ?, description = ?, technologies = ?, github_url = ?, live_demo_url = ?
            WHERE project_id = ? AND js_id = ?
        ");
        $saveStmt->bind_param(
            "sssssii",
            $values['title'],
            $values['description'],
            $values['technologies'],
            $values['github_url'],
            $values['live_demo_url'],
            $projectId,
            $jsId
        );
    }
    $saveStmt->execute();

    header("Location: profile.php?project_status=saved#projects-section", true, 303);
    exit();
} catch (mysqli_sql_exception $exception) {
    error_log("Job-seeker project update failed: " . $exception->getMessage());
    header("Location: profile.php?project_status=save#projects-section", true, 303);
    exit();
}
