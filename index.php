<?php
$pageTitle = '首页';
require_once 'includes/header.php';

$sort = $_GET['sort'] ?? 'date';
$order = sortField($sort);

$catFilter = (int)($_GET['category'] ?? 0);
$extraWhere = '';
if ($catFilter) {
    $extraWhere .= " AND a.category_id = $catFilter";
}

// 聚焦展示：最新发布的 5 篇文章（轮播）
$featuredArticles = db()->query("SELECT a.*, u.username, c.name as category_name
    FROM articles a
    LEFT JOIN users u ON a.author_id = u.id
    LEFT JOIN categories c ON a.category_id = c.id
    WHERE a.status = 'published'
    ORDER BY a.created_at DESC LIMIT 5")->fetchAll();
$featured = $featuredArticles[0] ?? null;
$featuredIds = array_column($featuredArticles, 'id');
// 只在文章总数 > 轮播数时才排除，避免列表变空
$totalPublished = (int)db()->query("SELECT COUNT(*) FROM articles WHERE status='published'")->fetchColumn();
$featExclude = (count($featuredIds) >= $totalPublished) ? '' : (" AND a.id NOT IN (" . implode(',', $featuredIds) . ")");

// 分页
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$countStmt = db()->query("SELECT COUNT(*) FROM articles a WHERE a.status = 'published' $extraWhere $featExclude");
$totalArticles = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalArticles / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = db()->query("SELECT a.*, u.username, c.name as category_name
    FROM articles a
    LEFT JOIN users u ON a.author_id = u.id
    LEFT JOIN categories c ON a.category_id = c.id
    WHERE a.status = 'published' $extraWhere $featExclude
    ORDER BY $order
    LIMIT $perPage OFFSET $offset");
$articles = $stmt->fetchAll();

// 侧边栏数据
$categories = db()->query("SELECT c.*, (SELECT COUNT(*) FROM articles WHERE category_id=c.id AND status='published') as article_count FROM categories c ORDER BY name")->fetchAll();
$recentSidebar = db()->query("SELECT id, title, created_at, views FROM articles WHERE status='published' ORDER BY created_at DESC LIMIT 5")->fetchAll();

?>

<?php if ($featuredArticles): ?>
<!-- 聚焦轮播 -->
<section class="carousel" id="featuredCarousel">
    <?php foreach ($featuredArticles as $i => $fa):
        $fSummary = $fa['summary'] ?: mb_substr(strip_tags($fa['content']), 0, 140);
        $fReading = max(1, ceil(mb_strlen(strip_tags($fa['content'])) / 500));
    ?>
    <div class="carousel-slide<?= $i === 0 ? ' active' : '' ?>" data-index="<?= $i ?>">
        <?php if ($fa['cover_image']): ?>
        <div class="carousel-bg">
            <img src="/myweb/<?= htmlspecialchars($fa['cover_image']) ?>" alt="" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
        </div>
        <?php endif; ?>
        <div class="carousel-overlay"></div>
        <div class="carousel-main">
            <?php if ($fa['category_name']): ?>
            <span class="carousel-badge"><?= htmlspecialchars($fa['category_name']) ?></span>
            <?php endif; ?>
            <h2 class="carousel-title">
                <a href="/myweb/article.php?id=<?= $fa['id'] ?>"><?= htmlspecialchars($fa['title']) ?></a>
            </h2>
            <p class="carousel-excerpt"><?= htmlspecialchars($fSummary) ?></p>
            <div class="carousel-meta">
                <span class="carousel-avatar"><?= mb_strtoupper(mb_substr(htmlspecialchars($fa['username']), 0, 1)) ?></span>
                <span><?= htmlspecialchars($fa['username']) ?></span>
                <span class="meta-dot">·</span>
                <span><?= date('m-d', strtotime($fa['created_at'])) ?></span>
                <span class="meta-dot">·</span>
                <span><?= $fReading ?> 分钟</span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- 右侧文章预览列表（填充留白） -->
    <div class="carousel-sidebar">
        <div class="carousel-sidebar-title">最新发布</div>
        <div class="carousel-sidebar-subtitle">基于 Reasonix 工作流重构</div>
        <?php foreach ($featuredArticles as $i => $fa): ?>
        <a href="/myweb/article.php?id=<?= $fa['id'] ?>" class="carousel-sidebar-item<?= $i === 0 ? ' active' : '' ?>" data-index="<?= $i ?>">
            <span class="carousel-sidebar-num"><?= sprintf('%02d', $i + 1) ?></span>
            <span class="carousel-sidebar-text"><?= htmlspecialchars($fa['title']) ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- 指示器 -->
    <div class="carousel-indicators">
        <?php foreach ($featuredArticles as $i => $_): ?>
        <button class="carousel-dot<?= $i === 0 ? ' active' : '' ?>" data-index="<?= $i ?>" aria-label="第<?= $i+1 ?>篇"></button>
        <?php endforeach; ?>
    </div>
</section>
<?php else: ?>
<section class="hero">
    <h1 class="hero-title"><?= htmlspecialchars($settings['site_desc'] ?? '探索与分享') ?></h1>
    <p class="hero-sub"><?= $siteName ?> — 一个安静写字的地方</p>
</section>
<?php endif; ?>

<!-- 两栏布局 -->
<div class="home-layout">
    <!-- 主内容 -->
    <div class="home-main">
        <!-- 排序栏 -->
        <div class="sort-bar">
            <span class="sort-label">排序</span>
            <a href="/myweb/?sort=date" class="sort-chip<?= $sort === 'date' ? ' active' : '' ?>">最新发布</a>
            <a href="/myweb/?sort=views" class="sort-chip<?= $sort === 'views' ? ' active' : '' ?>">最多浏览</a>
        </div>

        <!-- 文章列表 -->
        <div class="articles-list">
            <?php if (empty($articles)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📝</div>
                    <h3>还没有文章</h3>
                    <p>敬请期待第一篇内容</p>
                </div>
            <?php else: ?>
                <?php foreach ($articles as $article): ?>
                    <?php $summary = $article['summary'] ?: mb_substr(strip_tags($article['content']), 0, 200); ?>
                    <?php $readingTime = max(1, ceil(mb_strlen(strip_tags($article['content'])) / 500)); ?>
                    <article class="article-card">
                        <?php if ($article['cover_image']): ?>
                        <a href="/myweb/article.php?id=<?= $article['id'] ?>" class="article-card-cover">
                            <img src="/myweb/<?= htmlspecialchars($article['cover_image']) ?>" alt="" loading="lazy">
                            <?php if ($article['category_name']): ?>
                            <span class="article-card-badge"><?= htmlspecialchars($article['category_name']) ?></span>
                            <?php endif; ?>
                        </a>
                        <?php endif; ?>
                        <div class="article-card-body">
                            <?php if (!$article['cover_image'] && $article['category_name']): ?>
                            <span class="article-card-badge-static"><?= htmlspecialchars($article['category_name']) ?></span>
                            <?php endif; ?>
                            <h2 class="article-card-title">
                                <a href="/myweb/article.php?id=<?= $article['id'] ?>"><?= htmlspecialchars($article['title']) ?></a>
                            </h2>
                            <p class="article-card-excerpt"><?= htmlspecialchars($summary) ?></p>
                            <div class="article-card-meta">
                                <span><?= htmlspecialchars($article['username']) ?></span>
                                <span><?= date('Y-m-d', strtotime($article['created_at'])) ?></span>
                                <span><?= $readingTime ?> 分钟</span>
                                <span><?= $article['views'] ?> 次阅读</span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- 分页 -->
        <?= renderPagination($page, $totalPages) ?>
    </div>

    <!-- 侧栏 -->
    <aside class="home-sidebar">
        <div class="sidebar-card">
            <h3 class="sidebar-card-title">分类</h3>
            <?php if (empty($categories)): ?>
                <p style="color:var(--gray-400);font-size:0.85rem">暂无分类</p>
            <?php else: ?>
            <ul class="sidebar-list">
                <?php foreach ($categories as $c): ?>
                <li>
                    <a href="/myweb/?category=<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></a>
                    <span class="sidebar-badge"><?= $c['article_count'] ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <div class="sidebar-card">
            <h3 class="sidebar-card-title">最近文章</h3>
            <ul class="sidebar-list sidebar-recent">
                <?php foreach ($recentSidebar as $r): ?>
                <li>
                    <a href="/myweb/article.php?id=<?= $r['id'] ?>"><?= htmlspecialchars($r['title']) ?></a>
                    <small><?= date('m-d', strtotime($r['created_at'])) ?> · <?= $r['views'] ?> 阅读</small>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </aside>
</div>

<?php require_once 'includes/footer.php'; ?>
