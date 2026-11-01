<?php
require_once __DIR__ . "/../includes/session.php";

requireJobseeker();
requireCsrfPost();

$user_id = (int) $_SESSION['user_id'];
$error = "";
$filename = saveUploadedFile(
    "profile_pic",
    __DIR__ . "/../uploads/profile",
    ["image/jpeg" => "jpg", "image/png" => "png", "image/gif" => "gif", "image/webp" => "webp"],
    5 * 1024 * 1024,
    $error
);

if ($filename === "" || $filename === false) {
    http_response_code(400);
    exit(e($error !== "" ? $error : "Select a profile picture to upload."));
}

$stmt = $conn->prepare("UPDATE job_seekers SET profile_pic=? WHERE user_id=?");

$stmt->bind_param("si",$filename,$user_id);
if (!$stmt->execute()) {
    unlink(__DIR__ . "/../uploads/profile/" . $filename);
    error_log("Profile picture update failed: " . $stmt->error);
    http_response_code(500);
    exit("The profile picture could not be updated.");
}

$_SESSION['profile_pic'] = $filename;

header("Location: profile.php", true, 303);
exit();
?>