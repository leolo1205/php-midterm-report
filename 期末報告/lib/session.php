<?php
/**
 * Session 驗證工具
 * 提供玩家與管理員的統一 Session 驗證函式，以及 CSRF 保護工具
 */

function _base_url() {
    return defined('BASE_URL') ? BASE_URL : '';
}

function verify_player() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['player_id'])) {
        header('Location: ' . _base_url() . '/login.php');
        exit;
    }
    return (int)$_SESSION['player_id'];
}

function verify_admin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: ' . _base_url() . '/admin/login.php');
        exit;
    }
    return $_SESSION['admin_user'] ?? 'admin';
}

function get_player_id() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['player_id']) ? (int)$_SESSION['player_id'] : null;
}

function is_player_logged_in() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['player_id']);
}

function is_admin_logged_in() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// ── CSRF 保護工具 ──

function csrf_token() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 驗證 CSRF Token，支援 POST body 欄位與 X-CSRF-Token 標頭（AJAX 用）
 */
function csrf_verify() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $token = $_POST['csrf_token']
           ?? $_SERVER['HTTP_X_CSRF_TOKEN']
           ?? '';
    $session_token = $_SESSION['csrf_token'] ?? '';
    if ($session_token === '' || !hash_equals($session_token, $token)) {
        return false;
    }
    return true;
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}
