<?php
require_once __DIR__ . "/../includes/session.php";
requireAdmin();
require_once __DIR__ . "/../includes/admin-management.php";

$type = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
try {
    $headers = csvHeadersForType($type);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    exit("Invalid template type.");
}
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="victory-' . $type . '-template.csv"');
header('X-Content-Type-Options: nosniff');
$output = fopen('php://output', 'wb');
if ($output === false) {
    http_response_code(500);
    exit("Template could not be generated.");
}
fputcsv($output, $headers);
fclose($output);
