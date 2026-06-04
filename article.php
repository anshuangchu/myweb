<?php
require_once __DIR__ . '/includes/db_loader.php';

$id = $_GET['id'] ?? 0;
$stmt = db()->prepare("SELECT a.*, u.username, c.name as category_name
    FROM articles a
    LEFT JOIN users u ON a.author_id = u.id
    LEFT JOIN categories c ON a.category_id = c.id
    WHERE a.id = ? AND a.status = 'published'");
$stmt->execute([$id]);
$article = $stmt->fetch();

if (!$article) {
    $pageTitle = '文章不存在';
    require_once 'includes/header.php';
    echo '<div class="empty-state"><h2>文章不存在</h2><p><a href="/myweb/">返回首页</a></p></div>';
    require_once 'includes/footer.php';
    exit;
}

$pageTitle = htmlspecialchars($article['title']);
require_once 'includes/header.php';

// 增加浏览量（同会话只计一次）
if (empty($_SESSION['viewed_articles'][$id])) {
    db()->prepare("UPDATE articles SET views = views + 1 WHERE id = ?")->execute([$id]);
    $_SESSION['viewed_articles'][$id] = true;
}

// 获取标签
$tags = db()->prepare("SELECT t.id, t.name FROM tags t JOIN article_tags at ON t.id = at.tag_id WHERE at.article_id = ? ORDER BY t.name");
$tags->execute([$id]);
$articleTags = $tags->fetchAll();

// 阅读时间计算
$wordCount = mb_strlen(strip_tags($article['content']));
$readingTime = max(1, ceil($wordCount / 500));

// 上下篇
$prev = db()->prepare("SELECT id, title FROM articles WHERE status='published' AND created_at < ? ORDER BY created_at DESC LIMIT 1");
$prev->execute([$article['created_at']]);
$prevArticle = $prev->fetch();

$next = db()->prepare("SELECT id, title FROM articles WHERE status='published' AND created_at > ? ORDER BY created_at ASC LIMIT 1");
$next->execute([$article['created_at']]);
$nextArticle = $next->fetch();

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
                <span>📖 <?= $readingTime ?> 分钟</span>
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

    <div class="article-footer">
        <div class="article-footer-left">
            <a href="/myweb/" class="btn-sm">← 返回首页</a>
        </div>
        <div class="article-footer-right">
            <button class="btn-sm" onclick="window.scrollTo({top:0,behavior:'smooth'})">↑ 回到顶部</button>
        </div>
    </div>

    <?php if ($prevArticle || $nextArticle): ?>
    <nav class="article-nav">
        <div class="article-nav-prev">
            <?php if ($prevArticle): ?>
            <a href="/myweb/article.php?id=<?= $prevArticle['id'] ?>" class="article-nav-link">
                <span class="article-nav-label">← 上一篇</span>
                <span class="article-nav-title"><?= htmlspecialchars($prevArticle['title']) ?></span>
            </a>
            <?php endif; ?>
        </div>
        <div class="article-nav-next">
            <?php if ($nextArticle): ?>
            <a href="/myweb/article.php?id=<?= $nextArticle['id'] ?>" class="article-nav-link">
                <span class="article-nav-label">下一篇 →</span>
                <span class="article-nav-title"><?= htmlspecialchars($nextArticle['title']) ?></span>
            </a>
            <?php endif; ?>
        </div>
    </nav>
    <?php endif; ?>

    <!-- AI 推荐阅读 -->
    <div id="aiRecommendSection" class="recommend-section" style="display:none">
        <div class="recommend-header">
            <h3>📚 推荐阅读</h3>
            <span class="recommend-badge">AI 推荐</span>
        </div>
        <div id="recommendLoading" class="recommend-loading">
            <div class="ai-spinner"></div>
            <span>AI 正在分析相关文章…</span>
        </div>
        <div id="recommendList" class="recommend-list"></div>
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
                    el.className = 'recommend-item';
                    el.innerHTML = '<strong>' + item.title + '</strong>' +
                        (item.summary ? '<span>' + item.summary + '</span>' : '') +
                        '<small>' + item.date + '</small>';
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
