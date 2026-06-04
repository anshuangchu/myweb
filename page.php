<?php
require_once __DIR__ . '/includes/db_loader.php';

$slug = $_GET['slug'] ?? '';
$stmt = db()->prepare("SELECT * FROM pages WHERE slug = ? AND status = 'published'");
$stmt->execute([$slug]);
$page = $stmt->fetch();

if (!$page) {
    $pageTitle = '资料不存在';
    require_once 'includes/header.php';
    echo '<div class="empty-state"><h2>资料不存在</h2><p><a href="/myweb/">返回首页</a></p></div>';
    require_once 'includes/footer.php';
    exit;
}

$pageTitle = $page['title'];
require_once 'includes/header.php';
?>

<article class="article-detail">
    <nav class="breadcrumb">
        <a href="/myweb/">首页</a>
        <span class="sep">›</span>
        <a href="/myweb/pages.php">资料</a>
        <span class="sep">›</span>
        <span class="current"><?= htmlspecialchars($page['title']) ?></span>
    </nav>
    <h1><?= htmlspecialchars($page['title']) ?></h1>
    <div class="article-content">
        <?= $page['content'] ?>
    </div>
</article>

<?php require_once 'includes/footer.php'; ?>
