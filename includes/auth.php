<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/i18n.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        header('Location: /dashboard.php');
        exit;
    }
}

function currentUser() {
    return $_SESSION ?? [];
}

function isTeacher() {
    return ($_SESSION['role'] ?? '') === 'teacher';
}

function isStudent() {
    return ($_SESSION['role'] ?? '') === 'student';
}

function flash($msg, $type = 'success') {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// CSRF Protection
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Yêu cầu không hợp lệ (CSRF).');
    }
}

// Format a timestamp as relative Vietnamese time, e.g. "5 phút trước"
function timeAgoVi($timestamp) {
    $ts = is_numeric($timestamp) ? (int)$timestamp : strtotime($timestamp);
    $diff = time() - $ts;
    if ($diff < 60)         return 'vừa xong';
    if ($diff < 3600)       return floor($diff/60) . ' phút trước';
    if ($diff < 86400)      return floor($diff/3600) . ' giờ trước';
    if ($diff < 86400*7)    return floor($diff/86400) . ' ngày trước';
    return date('d/m/Y', $ts);
}
