<?php
require_once __DIR__ . "/functions.php";
// Database Configuration

error_reporting(E_ALL);
ini_set('display_errors', getenv('APP_ENV') === 'development' ? '1' : '0');
$host = getenv('DB_HOST') ?: "localhost";
$user = getenv('DB_USER') ?: "root";
$pass = getenv('DB_PASSWORD') ?: "";
$db   = getenv('DB_NAME') ?: "victory_db";

// Create connection using MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $exception) {
    error_log("Database connection failed: " . $exception->getMessage());
    http_response_code(500);
    exit("The application is temporarily unavailable. Please try again later.");
}
?>