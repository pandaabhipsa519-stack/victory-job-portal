<?php
require_once __DIR__ . "/config.php";

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/victory',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');

/*
|---------------------------------------------------------
| Check if user is logged in
|---------------------------------------------------------
*/
function isLoggedIn() {
    return isset($_SESSION['user_id'], $_SESSION['role'])
        && is_numeric($_SESSION['user_id'])
        && in_array($_SESSION['role'], ['jobseeker', 'employer', 'admin'], true);
}

/*
|---------------------------------------------------------
| Require Login
|---------------------------------------------------------
*/
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /victory/auth/login.php");
        exit();
    }

    static $validatedUserId = null;
    $userId = (int) $_SESSION['user_id'];
    if ($validatedUserId === $userId) {
        return;
    }

    global $conn;
    $stmt = $conn->prepare("SELECT role, account_status FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();

    if (!$account || $account['account_status'] !== 'active' || $account['role'] !== $_SESSION['role']) {
        session_unset();
        session_destroy();
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => 'Lax',
            ]);
        }
        http_response_code(403);
        exit("This account is inactive or not yet set up.");
    }

    $validatedUserId = $userId;
}

/*
|---------------------------------------------------------
| Role Check Functions
|---------------------------------------------------------
*/
function requireJobseeker() {
    requireLogin();
    if ($_SESSION['role'] !== 'jobseeker') {
        http_response_code(403);
        echo "Access denied.";
        exit();
    }
}

function requireEmployer() {
    requireLogin();
    if ($_SESSION['role'] !== 'employer') {
        http_response_code(403);
        echo "Access denied.";
        exit();
    }

    global $conn;
    $userId = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("
        SELECT e.emp_id, c.status AS company_status
        FROM employers e
        LEFT JOIN companies c ON c.company_id = e.company_id
        WHERE e.user_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $employer = $stmt->get_result()->fetch_assoc();
    if (!$employer || $employer['company_status'] === 'inactive') {
        http_response_code(403);
        exit("Employer profile is unavailable.");
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo "Access denied.";
        exit();
    }
}


/*
|---------------------------------------------------------
| Logout Function
|---------------------------------------------------------
*/
function logout() {
    session_unset();
    session_destroy();
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => 'Lax',
        ]);
    }
    header("Location: /victory/auth/login.php");
    exit();
}
