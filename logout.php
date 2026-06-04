<?php
require_once __DIR__ . '/includes/db_loader.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// CSRF 保护：只接受 POST 请求或带 token 的 GET 跳转
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // 支持 GET 方式快速退出（不带 CSRF token 时仅重定向到首页）
    // 使用 POST 方式退出更安全
    if (!isset($_GET['token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_GET['token'])) {
        header("Location: /myweb/");
        exit;
    }
}

// POST 方式时验证 CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
}

$_SESSION = [];
$cookieParams = session_get_cookie_params();
setcookie(session_name(), '', time() - 42000,
    $cookieParams['path'], $cookieParams['domain'],
    $cookieParams['secure'], $cookieParams['httponly']
);
session_destroy();
header("Location: /myweb/");
exit;
