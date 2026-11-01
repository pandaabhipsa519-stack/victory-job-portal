<?php
require_once __DIR__ . "/../includes/session.php";

requireJobseeker();
requireCsrfPost();

$user_id = (int) $_SESSION['user_id'];

$fields = ['name', 'phone', 'gender', 'dob', 'education', 'state', 'location', 'about'];
foreach ($fields as $field) {
    if (!isset($_POST[$field]) || !is_string($_POST[$field]) || !mb_check_encoding($_POST[$field], 'UTF-8')) {
        header("Location: profile.php?status=invalid", true, 303);
        exit();
    }
}

$values = [];
$maxLengths = [
    'name' => 100,
    'phone' => 15,
    'gender' => 10,
    'dob' => 10,
    'education' => 100,
    'state' => 100,
    'location' => 100,
    'about' => 10000,
];
foreach ($maxLengths as $field => $maxLength) {
    $values[$field] = trim($_POST[$field]);
    if (mb_strlen($values[$field], 'UTF-8') > $maxLength) {
        header("Location: profile.php?status=invalid", true, 303);
        exit();
    }
}

$validName = $values['name'] !== ''
    && preg_match('/[\x00-\x1F\x7F]/u', $values['name']) !== 1;
$validPhone = $values['phone'] === ''
    || (
        preg_match('/\A\+?[0-9().\- ]+\z/u', $values['phone']) === 1
        && preg_match_all('/[0-9]/', $values['phone']) >= 7
        && preg_match_all('/[0-9]/', $values['phone']) <= 15
    );
$validDob = true;
if ($values['dob'] !== '') {
    $dobDate = DateTimeImmutable::createFromFormat('!Y-m-d', $values['dob']);
    $dateErrors = DateTimeImmutable::getLastErrors();
    $validDob = $dobDate !== false
        && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
        && $dobDate->format('Y-m-d') === $values['dob']
        && $dobDate <= new DateTimeImmutable('today');
}

$hasInvalidControl = preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $values['about']) === 1;
foreach (['education', 'state', 'location'] as $field) {
    if (preg_match('/[\x00-\x1F\x7F]/u', $values[$field]) === 1) {
        $hasInvalidControl = true;
        break;
    }
}

if (!$validName
    || !$validPhone
    || !in_array($values['gender'], ["", "Male", "Female", "Other"], true)
    || !$validDob
    || $hasInvalidControl) {
    header("Location: profile.php?status=invalid", true, 303);
    exit();
}

try {
    $stmt = $conn->prepare("
    UPDATE job_seekers
    SET name=?, phone=?, gender=?, dob=?, education=?, state=?, location=?, about=?
    WHERE user_id=?
    ");
    $stmt->bind_param(
        "ssssssssi",
        $values['name'],
        $values['phone'],
        $values['gender'],
        $values['dob'],
        $values['education'],
        $values['state'],
        $values['location'],
        $values['about'],
        $user_id
    );
    $stmt->execute();
} catch (mysqli_sql_exception $exception) {
    error_log("Job seeker profile update failed: " . $exception->getMessage());
    header("Location: profile.php?status=save", true, 303);
    exit();
}

header("Location: profile.php?status=updated", true, 303);
exit();
?>