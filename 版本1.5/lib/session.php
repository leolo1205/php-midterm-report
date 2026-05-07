<?php
/**
 * Session 驗證工具
 * 提供玩家與管理員的統一 Session 驗證函式
 */

function verify_player() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['player_id'])) {
        header('Location: /targame/login.php');
        exit;
    }
    return (int)$_SESSION['player_id'];
}

function verify_admin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: /targame/admin/login.php');
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
