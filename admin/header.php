<?php
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 0,
    'path' => $cookieParams['path'],
    'domain' => $cookieParams['domain'],
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
require_once __DIR__ . '/db_loader.php';

// 批量加载站点设置（带 session 缓存）
if (empty($_SESSION['settings_cache'])) {
    $settings = [];
    $stmt = db()->query("SELECT key_name, value FROM settings");
    foreach ($stmt->fetchAll() as $row) {
        $settings[$row['key_name']] = $row['value'];
    }
    $_SESSION['settings_cache'] = $settings;
} else {
    $settings = $_SESSION['settings_cache'];
}
$siteName = htmlspecialchars($settings['site_name'] ?? '我的网站');

// 获取活跃公告
$announcement = db()->query("SELECT content FROM announcements WHERE status='active' LIMIT 1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? '我的网站' ?> - <?= $siteName ?></title>
    <link rel="stylesheet" href="/myweb/css/style.css">
    <script src="/myweb/js/script.js" defer></script>
</head>
<body>
<?php if ($announcement): ?>
<div class="announcement-bar"><?= htmlspecialchars($announcement) ?></div>
<?php endif; ?>
<nav class="navbar">
    <div class="container">
        <a href="/myweb/" class="navbar-brand"><?= $siteName ?></a>
        <ul class="nav-menu">
            <li><a href="/myweb/">首页</a></li>
            <li><a href="/myweb/pages.php">资料</a></li>
            <li><a href="/myweb/search.php">搜索</a></li>
            <?php if (isLoggedIn()): ?>
                <?php if (hasRole('super_admin', 'admin', 'editor')): ?>
                    <li><a href="/myweb/admin/">管理后台</a></li>
                <?php endif; ?>
                <li><a href="/myweb/logout.php">退出 (<?= htmlspecialchars(currentUser()['username']) ?>)</a></li>
            <?php else: ?>
                <li><a href="/myweb/login.php">登录</a></li>
                <li><a href="/myweb/register.php">注册</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>
<main class="container">
