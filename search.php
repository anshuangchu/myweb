<?php
$pageTitle = '搜索';
require_once 'includes/header.php';
require_once __DIR__ . '/includes/search_service.php';

$q = trim($_GET['q'] ?? '');
?>

<div class="search-page">
    <!-- 顶部搜索栏 — 紧凑但大气 -->
    <div class="search-hero-bg">
        <div class="search-hero-inner">
            <h1 class="search-hero-title">探索</h1>
            <form class="search-form-lg" method="get" action="/myweb/search.php">
                <svg class="search-form-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="q" placeholder="输入关键词搜索文章..." value="<?= htmlspecialchars($q) ?>" autofocus>
                <button type="submit">搜索</button>
            </form>
        </div>
    </div>

    <?php if ($q):
        $articles = SearchService::searchArticles($q);
        $total    = count($articles);
    ?>

    <div class="search-results">
        <!-- 结果摘要 -->
        <div class="search-summary">
            <span>找到 <strong><?= $total ?></strong> 篇文章</span>
            <button id="aiSearchBtn" class="ai-search-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M12 2a4 4 0 0 1 4 4v2a4 4 0 0 1-8 0V6a4 4 0 0 1 4-4z"/><path d="M8 14v-2a4 4 0 0 1 8 0v2"/><circle cx="12" cy="12" r="10"/></svg>
                AI 增强
            </button>
        </div>

        <div id="aiSearchStatus" class="search-ai-status hidden"></div>
        <div id="aiSearchResults" class="hidden"></div>

        <?php if ($total === 0): ?>
        <div class="search-empty-state">
            <span class="search-empty-icon">🔍</span>
            <h3>未找到相关内容</h3>
            <p>试试更换更简短的关键词</p>
        </div>
        <?php endif; ?>

        <?php if (!empty($articles)): ?>
        <div class="glow-grid">
            <?php foreach ($articles as $a): ?>
            <a href="/myweb/article.php?id=<?= $a['id'] ?>" class="glow-card">
                <?php if ($a['cover_image']): ?>
                <div class="glow-card-cover"><img src="/myweb/<?= htmlspecialchars($a['cover_image']) ?>" alt="" loading="lazy"></div>
                <?php endif; ?>
                <div class="glow-card-body">
                    <h3><?= htmlspecialchars($a['title']) ?></h3>
                    <p><?= htmlspecialchars($a['summary'] ?: mb_substr(strip_tags($a['content']), 0, 120)) ?></p>
                    <div class="glow-card-meta">
                        <span><?= htmlspecialchars($a['username']) ?></span>
                        <span class="meta-dot">·</span>
                        <span><?= date('m-d', strtotime($a['created_at'])) ?></span>
                        <?php if ($a['category_name']): ?>
                        <span class="meta-dot">·</span>
                        <span class="glow-card-tag"><?= htmlspecialchars($a['category_name']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script src="/myweb/js/search.js" defer></script>
<?php require_once 'includes/footer.php'; ?>
