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
$settingsVersion = (int) (db()->query("SELECT value FROM settings WHERE key_name = '_version'")->fetchColumn() ?: 0);
if (empty($_SESSION['settings_cache']) || ($_SESSION['settings_cache_version'] ?? 0) !== $settingsVersion) {
    $settings = [];
    $stmt = db()->query("SELECT key_name, value FROM settings WHERE key_name != '_version'");
    foreach ($stmt->fetchAll() as $row) {
        $settings[$row['key_name']] = $row['value'];
    }
    $_SESSION['settings_cache'] = $settings;
    $_SESSION['settings_cache_version'] = $settingsVersion;
} else {
    $settings = $_SESSION['settings_cache'];
}
$siteName = htmlspecialchars($settings['site_name'] ?? '我的网站');
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 获取活跃公告
$announcement = db()->query("SELECT content FROM announcements WHERE status='active' LIMIT 1")->fetchColumn();

// 安全响应头
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' https://fonts.gstatic.com; connect-src 'self'; frame-src 'self' https://view.officeapps.live.com; frame-ancestors 'none'");

// 导航项
$navLinks = [
    ['href' => '/myweb/',          'label' => '首页'],
    ['href' => '/myweb/pages.php',  'label' => '资料'],
    ['href' => '/myweb/search.php', 'label' => '搜索'],
    ['href' => '/myweb/files.php',  'label' => '文件'],
];
if (isLoggedIn() && hasRole('super_admin', 'admin', 'editor')) {
    $navLinks[] = ['href' => '/myweb/admin/', 'label' => '管理'];
}

function isActiveNav($href, $currentPath) {
    if ($href === '/myweb/' && ($currentPath === '/myweb/' || $currentPath === '/myweb/index.php')) return true;
    return str_starts_with($currentPath, $href);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <title><?= $pageTitle ?? '首页' ?> — <?= $siteName ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+SC:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/myweb/css/style.css">
    <script src="/myweb/js/script.js" defer></script>
</head>
<body>

<!-- ===== 顶部导航栏 ===== -->
<header class="topbar">
    <!-- 汉堡菜单（移动端） -->
    <button class="topbar-menu-btn" id="navToggle" aria-label="菜单">
        <span></span><span></span><span></span>
    </button>

    <!-- Logo -->
    <a href="/myweb/" class="topbar-logo">
        <span class="topbar-logo-dot"></span>
        <?= $siteName ?>
    </a>

    <!-- 导航链接（桌面端） -->
    <nav class="topbar-nav">
        <?php foreach ($navLinks as $link): ?>
        <a href="<?= $link['href'] ?>" class="topbar-nav-link<?= isActiveNav($link['href'], $currentPath) ? ' active' : '' ?>">
            <?= $link['label'] ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- 弹性空间 -->
    <div class="topbar-spacer"></div>

    <!-- 搜索 -->
    <form action="/myweb/search.php" method="get" class="topbar-search">
        <svg class="topbar-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" name="q" placeholder="搜索文章...">
    </form>

    <!-- 用户区 -->
    <?php if (isLoggedIn()): ?>
        <div class="topbar-user">
            <span class="topbar-user-avatar"><?= htmlspecialchars(mb_substr(currentUser()['username'] ?? 'U', 0, 1)) ?></span>
            <span><?= htmlspecialchars(currentUser()['username'] ?? '') ?></span>
        </div>
        <a href="/myweb/logout.php" class="topbar-nav-link" style="font-size:0.8rem;color:var(--gray-500)"
           onclick="event.preventDefault();document.getElementById('logoutForm').submit();">退出</a>
        <form id="logoutForm" method="post" action="/myweb/logout.php" style="display:none"><?php if (function_exists('csrfField')) echo csrfField(); ?></form>
    <?php else: ?>
        <a href="/myweb/login.php" class="topbar-login">登录</a>
    <?php endif; ?>
</header>

<!-- 移动端导航面板 -->
<nav class="mobile-nav" id="mobileNav">
    <?php foreach ($navLinks as $link): ?>
    <a href="<?= $link['href'] ?>" class="<?= isActiveNav($link['href'], $currentPath) ? 'active' : '' ?>"><?= $link['label'] ?></a>
    <?php endforeach; ?>
    <?php if (isLoggedIn()): ?>
        <a href="/myweb/logout.php" onclick="event.preventDefault();document.getElementById('logoutForm').submit();">退出登录</a>
    <?php else: ?>
        <a href="/myweb/login.php">登录</a>
        <a href="/myweb/register.php">注册</a>
    <?php endif; ?>
</nav>

<!-- 主内容区 -->
<main class="main-content">
<?php if ($announcement && ($currentPath === '/myweb/' || $currentPath === '/myweb/index.php')): ?>
<div class="announcement-bar"><?= htmlspecialchars($announcement) ?></div>
<?php endif; ?>
