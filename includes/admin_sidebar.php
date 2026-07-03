<?php
$page = basename($_SERVER['PHP_SELF']);
function isActive(string ...$names): string {
    global $page;
    return in_array($page, $names) ? ' class="active"' : '';
}
?>
<div class="sidebar-section">
    <div class="sidebar-section-title">内容管理</div>
    <ul>
        <li><a href="/myweb/admin/"<?= isActive('index.php') ?>>控制台</a></li>
        <li><a href="/myweb/admin/articles.php"<?= isActive('articles.php', 'article_edit.php') ?>>文章管理</a></li>
        <li><a href="/myweb/admin/files.php"<?= isActive('files.php') ?>>文件管理</a></li>
    </ul>
</div>

<div class="sidebar-divider"></div>

<div class="sidebar-section">
    <div class="sidebar-section-title">系统设置</div>
    <ul>
        <?php if (hasRole('super_admin', 'admin')): ?>
        <li><a href="/myweb/admin/categories.php"<?= isActive('categories.php') ?>>分类管理</a></li>
        <li><a href="/myweb/admin/links.php"<?= isActive('links.php') ?>>友链管理</a></li>
        <li><a href="/myweb/admin/announcements.php"<?= isActive('announcements.php') ?>>公告管理</a></li>
        <li><a href="/myweb/admin/users.php"<?= isActive('users.php') ?>>用户管理</a></li>
        <?php endif; ?>
        <?php if (hasRole('super_admin')): ?>
        <li><a href="/myweb/admin/settings.php"<?= isActive('settings.php') ?>>站点设置</a></li>
        <?php endif; ?>
    </ul>
</div>

<div class="sidebar-divider"></div>

<ul>
    <li><a href="/myweb/" class="sidebar-back">← 返回前台</a></li>
</ul>
