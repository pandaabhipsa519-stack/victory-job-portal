<?php
require_once __DIR__ . "/functions.php";

function createAccountSetupToken($conn, $userId)
{
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + 86400);
    $stmt = $conn->prepare("
        UPDATE users
        SET setup_token_hash = ?, setup_token_expires_at = ?
        WHERE user_id = ? AND account_status = 'pending_setup'
    ");
    $stmt->bind_param("ssi", $tokenHash, $expiresAt, $userId);
    $stmt->execute();
    if ($stmt->affected_rows !== 1) {
        throw new RuntimeException("A setup link could not be issued for this account.");
    }

    return $token;
}

function sendAccountSetupEmail($email, $token)
{
    $baseUrl = rtrim(getenv('APP_BASE_URL') ?: 'http://localhost/victory', '/');
    $parts = parse_url($baseUrl);
    $host = strtolower((string) ($parts['host'] ?? ''));
    $localHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    if (!in_array($parts['scheme'] ?? '', ['https', 'http'], true)
        || empty($parts['host'])
        || (($parts['scheme'] ?? '') !== 'https' && !$localHost)) {
        error_log("Account setup email has an invalid or non-HTTPS application base URL.");
        return false;
    }

    $from = getenv('APP_MAIL_FROM') ?: 'noreply@localhost';
    if (!filter_var($from, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $from)) {
        $from = 'noreply@localhost';
    }
    $link = $baseUrl . "/auth/setup-account.php?token=" . rawurlencode($token);
    $headers = "From: " . $from . "\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    $body = "Set a password for your Victory account using this one-time link within 24 hours:\r\n\r\n"
        . $link . "\r\n\r\nIf you did not expect this message, you may ignore it.";

    if (!mail($email, "Set up your Victory account", $body, $headers)) {
        error_log("Account setup email delivery failed.");
        return false;
    }

    return true;
}

function writeAdminAudit($conn, $action, $entityType, $entityId, $summary, array $details = [])
{
    $actorId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    $entityId = $entityId === null ? null : (int) $entityId;
    $detailsJson = $details === [] ? null : json_encode($details, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($detailsJson === false) {
        throw new RuntimeException("Audit details could not be encoded.");
    }

    $stmt = $conn->prepare("
        INSERT INTO admin_audit_log
            (actor_user_id, action, entity_type, entity_id, summary, details_json)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ississ", $actorId, $action, $entityType, $entityId, $summary, $detailsJson);
    $stmt->execute();
}
