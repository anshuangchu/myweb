<?php
$pageTitle = '文件浏览';
require_once 'includes/header.php';
require_once __DIR__ . '/includes/file_service.php';

if (!isLoggedIn()) {
    echo '<div class="empty-state"><h3>请先登录</h3><p><a href="/myweb/login.php?redirect=/myweb/files.php">去登录</a></p></div>';
    require_once 'includes/footer.php'; exit;
}

$typeFilter  = $_GET['type'] ?? 'all';
$searchQuery = trim($_GET['q'] ?? '');
$result     = FileService::getFiles($typeFilter, $searchQuery);
$files      = $result['files'];
$categories = $result['categories'];
$stats      = $result['stats'];
$catDefs    = FileService::getCategories();
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 30;
$totalFiles = count($files);
$totalPages = max(1, (int) ceil($totalFiles / $perPage));
$page       = min($page, $totalPages);
$pageFiles  = array_slice($files, ($page - 1) * $perPage, $perPage);
$paginationBase = '/myweb/files.php?type=' . urlencode($typeFilter) . ($searchQuery ? '&q=' . urlencode($searchQuery) : '') . '&page=%d';
?>

<div class="files-page">
    <div class="files-hero-bg">
    <form class="files-toolbar" method="get">
        <div class="files-search">
            <svg class="files-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" name="q" placeholder="搜索文件名..." value="<?= htmlspecialchars($searchQuery) ?>">
            <?php if ($typeFilter !== 'all'): ?><input type="hidden" name="type" value="<?= htmlspecialchars($typeFilter) ?>"><?php endif; ?>
        </div>
        <div class="files-tabs">
            <a href="/myweb/files.php?type=all<?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>" class="files-tab <?= $typeFilter === 'all' ? 'active' : '' ?>">全部 <span class="count"><?= $categories['all']['count'] ?></span></a>
            <?php foreach ($catDefs as $key => $cat): ?>
            <a href="/myweb/files.php?type=<?= $key ?><?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>" class="files-tab <?= $typeFilter === $key ? 'active' : '' ?>"><?= $cat['icon'] ?> <?= $cat['label'] ?> <span class="count"><?= $categories[$key]['count'] ?></span></a>
            <?php endforeach; ?>
        </div>
    </form>
    </div>

    <div class="files-bar">
        <span class="files-bar-stat"><strong><?= $stats['total'] ?></strong> 个文件 · <strong><?= formatSize($stats['totalSize']) ?></strong></span>
    </div>

    <?php if (empty($pageFiles)): ?>
    <div class="empty-state"><div class="empty-state-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40" height="40"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></div><p><?= $searchQuery ? '没有匹配的文件' : '暂无文件' ?></p></div>
    <?php else: ?>

    <div class="files-card-grid">
        <?php foreach ($pageFiles as $f): ?>
        <?php $viewUrl = '/myweb/view.php?file=' . rawurlencode($f['name']); ?>
        <a href="<?= $viewUrl ?>" class="glow-card glow-card-file">
            <div class="glow-card-cover glow-card-cover-file">
                <?php if ($f['is_image']): ?>
                    <img src="/myweb/uploads/<?= rawurlencode($f['name']) ?>" alt="" loading="lazy">
                <?php else: ?>
                    <span class="file-card-icon-large"><?= $f['icon'] ?></span>
                <?php endif; ?>
                <span class="file-card-ext-badge"><?= strtoupper($f['ext']) ?></span>
            </div>
            <div class="glow-card-body">
                <h3><?= htmlspecialchars($f['name']) ?></h3>
                <div class="glow-card-meta">
                    <span><?= formatSize($f['size']) ?></span>
                    <span class="meta-dot">·</span>
                    <span><?= date('Y-m-d', $f['time']) ?></span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <?= renderPagination($page, $totalPages, $paginationBase) ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
