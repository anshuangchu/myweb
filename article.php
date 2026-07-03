<?php
require_once __DIR__ . '/includes/db_loader.php';
require_once __DIR__ . '/includes/article_service.php';

$id      = (int)($_GET['id'] ?? 0);
$article = ArticleService::getArticleById($id);

if (!$article || $article['status'] !== 'published') {
    $pageTitle = '文章不存在';
    require_once 'includes/header.php';
    echo '<div class="empty-state"><h2>文章不存在</h2><p><a href="/myweb/">返回首页</a></p></div>';
    require_once 'includes/footer.php';
    exit;
}

$pageTitle = htmlspecialchars($article['title']);
require_once 'includes/header.php';

// 浏览量（同会话只计一次）
ArticleService::incrementViews($id);

// 标签
$articleTags = ArticleService::getArticleTags($id);

// 阅读时间
$wordCount   = mb_strlen(strip_tags($article['content']));
$readingTime = max(1, ceil($wordCount / 500));

// 上下篇
$adjacent    = ArticleService::getAdjacentArticles($id, $article['created_at']);
$prevArticle = $adjacent['prev'];
$nextArticle = $adjacent['next'];

// 封面图 URL（用于 SEO）
$coverUrl = $article['cover_image'] ? ('/myweb/' . $article['cover_image']) : '';
?>
<!-- 阅读进度条 -->
<div class="reading-progress"><div class="reading-progress-bar" id="readingBar"></div></div>

<article class="article-detail">
    <div class="article-header">
        <!-- 面包屑 -->
        <nav class="breadcrumb">
            <a href="/myweb/">首页</a>
            <span class="sep">/</span>
            <?php if ($article['category_name']): ?>
            <a href="/myweb/?category=<?= $article['category_id'] ?>"><?= htmlspecialchars($article['category_name']) ?></a>
            <span class="sep">/</span>
            <?php endif; ?>
            <span class="current"><?= htmlspecialchars($article['title']) ?></span>
        </nav>

        <!-- 标题 -->
        <h1><?= htmlspecialchars($article['title']) ?></h1>

        <!-- 作者卡片 -->
        <div class="article-author">
            <span class="article-author-avatar"><?= mb_strtoupper(mb_substr(htmlspecialchars($article['username']), 0, 1)) ?></span>
            <div class="article-author-info">
                <span class="article-author-name"><?= htmlspecialchars($article['username']) ?></span>
            </div>
        </div>

        <!-- 标签 -->
        <?php if ($articleTags): ?>
        <div class="article-tags">
            <?php foreach ($articleTags as $tag): ?>
            <a href="/myweb/search.php?q=<?= urlencode($tag['name']) ?>" class="article-tag"><?= htmlspecialchars($tag['name']) ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- 元信息 + 操作 -->
        <div class="article-meta-bar">
            <div class="article-meta-stats">
                <span>📅 <?= date('Y-m-d', strtotime($article['created_at'])) ?></span>
                <span class="meta-dot">·</span>
                <span>👁️ <?= number_format($article['views']) ?> 阅读</span>
                <span class="meta-dot">·</span>
                <span><?= number_format($wordCount) ?> 字</span>
            </div>
            <button class="btn-share" onclick="copyLink()" title="复制链接">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                复制链接
            </button>
        </div>
    </div>

    <?php if ($article['cover_image']): ?>
        <div class="article-detail-cover">
            <img src="/myweb/<?= htmlspecialchars($article['cover_image']) ?>" alt="<?= htmlspecialchars($article['title']) ?>">
        </div>
    <?php endif; ?>

    <?php if (!empty($article['summary'])): ?>
    <div class="article-summary">
        <span class="summary-label">摘要</span>
        <p><?= htmlspecialchars($article['summary']) ?></p>
    </div>
    <?php endif; ?>

    <div class="article-content-wrap">
        <div class="article-content">
            <?= $article['content'] ?>
        </div>
    </div>

    <!-- 文章底部操作栏 -->
    <div class="article-actions-bar">
        <a href="/myweb/" class="article-action-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            返回首页
        </a>
        <button class="article-action-btn" onclick="window.scrollTo({top:0,behavior:'smooth'})">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
            回到顶部
        </button>
        <button class="article-action-btn" onclick="copyLink()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            复制链接
        </button>
    </div>

    <?php if ($prevArticle || $nextArticle): ?>
    <!-- 上下篇导航卡片 -->
    <nav class="article-nav-cards">
        <?php if ($prevArticle): ?>
        <a href="/myweb/article.php?id=<?= $prevArticle['id'] ?>" class="glow-card article-nav-card">
            <div class="glow-card-body">
                <span class="article-nav-label">← 上一篇</span>
                <h3><?= htmlspecialchars($prevArticle['title']) ?></h3>
            </div>
        </a>
        <?php else: ?>
        <div class="article-nav-card article-nav-empty"></div>
        <?php endif; ?>

        <?php if ($nextArticle): ?>
        <a href="/myweb/article.php?id=<?= $nextArticle['id'] ?>" class="glow-card article-nav-card article-nav-card-next">
            <div class="glow-card-body">
                <span class="article-nav-label">下一篇 →</span>
                <h3><?= htmlspecialchars($nextArticle['title']) ?></h3>
            </div>
        </a>
        <?php else: ?>
        <div class="article-nav-card article-nav-empty"></div>
        <?php endif; ?>
    </nav>
    <?php endif; ?>

    <!-- AI 推荐阅读 — 卡片网格 -->
    <div id="aiRecommendSection" class="article-recommend" style="display:none">
        <div class="search-section-head">
            <h3>推荐阅读</h3>
            <span class="ai-search-badge">AI 推荐</span>
        </div>
        <div id="recommendLoading" class="recommend-loading" style="text-align:center;padding:var(--sp-8)">
            <div class="ai-spinner"></div>
            <span style="color:var(--text-muted);font-size:0.85rem">AI 正在分析相关文章…</span>
        </div>
        <div id="recommendList" class="glow-grid"></div>
    </div>
</article>

<script>
// 阅读进度条
window.addEventListener('scroll', function() {
    const scrollTop = window.scrollY;
    const docHeight = document.documentElement.scrollHeight - window.innerHeight;
    const progress = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;
    document.getElementById('readingBar').style.width = progress + '%';
});

// 复制链接
function copyLink() {
    const url = window.location.href;
    navigator.clipboard.writeText(url).then(function() {
        const btn = document.querySelector('.btn-share');
        const orig = btn.textContent;
        btn.textContent = '✅ 已复制';
        setTimeout(() => { btn.textContent = orig; }, 2000);
    }).catch(function() {
        prompt('手动复制链接:', url);
    });
}

// HTML 转义
function escHTML(str) { var d = document.createElement('div'); d.appendChild(document.createTextNode(str)); return d.innerHTML; }

// AI 推荐阅读
document.addEventListener('DOMContentLoaded', function() {
    const articleId = <?= $id ?>;
    if (!articleId) return;

    fetch('/myweb/ai_recommend.php?id=' + articleId)
        .then(r => r.json())
        .then(res => {
            document.getElementById('recommendLoading').style.display = 'none';
            if (res.success && res.data && res.data.length > 0) {
                const list = document.getElementById('recommendList');
                res.data.forEach(item => {
                    const el = document.createElement('a');
                    el.href = '/myweb/article.php?id=' + item.id;
                    el.className = 'glow-card';
                    el.innerHTML = '<div class="glow-card-body"><h3>' + escHTML(item.title) + '</h3>' +
                        (item.summary ? '<p>' + escHTML(item.summary) + '</p>' : '') +
                        '<div class="glow-card-meta"><span>' + escHTML(item.date) + '</span></div></div>';
                    list.appendChild(el);
                });
                document.getElementById('aiRecommendSection').style.display = 'block';
            }
        })
        .catch(() => {
            document.getElementById('recommendLoading').style.display = 'none';
        });
});
</script>
<?php require_once 'includes/footer.php'; ?>
