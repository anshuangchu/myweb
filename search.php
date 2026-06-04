<?php
$pageTitle = '搜索';
require_once 'includes/header.php';

$q = trim($_GET['q'] ?? '');
$results = [];
$searchStats = '';

if ($q) {
    $keyword = '%' . $q . '%';

    // 搜索文章
    $stmt = db()->prepare("SELECT a.*, u.username, c.name as category_name
        FROM articles a
        LEFT JOIN users u ON a.author_id = u.id
        LEFT JOIN categories c ON a.category_id = c.id
        WHERE a.status = 'published' AND (a.title LIKE ? OR a.summary LIKE ? OR a.content LIKE ?)
        ORDER BY a.created_at DESC
        LIMIT 50");
    $stmt->execute([$keyword, $keyword, $keyword]);
    $articles = $stmt->fetchAll();

    // 搜索资料
    $stmt = db()->prepare("SELECT * FROM pages WHERE status='published' AND (title LIKE ? OR content LIKE ?) ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$keyword, $keyword]);
    $pages = $stmt->fetchAll();

    $total = count($articles) + count($pages);
    $searchStats = "找到 <strong>$total</strong> 条关于 \"<strong>" . htmlspecialchars($q) . "</strong>\" 的结果";
}

?>

<style>
.search-page { max-width: 800px; margin: 0 auto; padding: 24px 0; }
.search-form { display: flex; gap: 10px; margin-bottom: 24px; }
.search-form input {
    flex: 1; padding: 12px 16px; border: 1px solid var(--border);
    border-radius: 6px; font-size: 1rem; background: var(--bg-card);
    color: var(--text-primary); transition: border .2s;
}
.search-form input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(88,166,255,0.15); }
.search-form button {
    padding: 12px 28px;
    background: #238636;
    border-color: rgba(240,246,252,0.1);
    color: #fff;
    border: 1px solid;
    border-radius: 6px;
    font-size: 0.95rem;
    font-weight: 500;
    cursor: pointer;
    transition: background .2s;
    white-space: nowrap;
}
.search-form button:hover { background: #2ea043; }
.search-stats { color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 20px; padding: 12px 16px; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); }
.search-section-title {
    font-size: 1.05rem;
    margin: 28px 0 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-primary);
    font-weight: 600;
}
.search-section-title .count { font-size: 0.8rem; color: var(--text-muted); font-weight: normal; }
.search-hit { background: rgba(210,153,34,0.2); color: var(--warning); padding: 0 2px; border-radius: 2px; }
.search-empty { text-align: center; padding: 80px 20px; color: var(--text-muted); }
.search-empty .icon { font-size: 3.5rem; margin-bottom: 16px; }
.search-page .page-result:hover { border-color: rgba(88,166,255,0.15) !important; transform: translateY(-2px); box-shadow: 0 4px 20px rgba(0,0,0,0.2); }

/* AI 搜索增强 */
.search-ai-bar { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
.btn-ai-search {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 18px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 0.85rem;
    cursor: pointer;
    background: linear-gradient(135deg, rgba(88,166,255,0.08), rgba(163,113,247,0.08));
    color: var(--accent);
    font-weight: 500;
    transition: all .2s;
}
.btn-ai-search:hover { background: linear-gradient(135deg, rgba(88,166,255,0.15), rgba(163,113,247,0.15)); border-color: var(--accent); }
.btn-ai-search:disabled { opacity: 0.5; cursor: not-allowed; }
.search-ai-status { font-size: 0.85rem; color: var(--text-muted); }
.ai-search-badge { font-size: 0.7rem; padding: 1px 6px; border-radius: 8px; background: rgba(163,113,247,0.15); color: var(--purple); margin-left: 8px; font-weight: 500; }
.ai-keywords { display: flex; gap: 6px; flex-wrap: wrap; margin: 12px 0; }
.ai-keyword { padding: 3px 10px; background: rgba(88,166,255,0.08); border: 1px solid rgba(88,166,255,0.15); border-radius: 12px; font-size: 0.8rem; color: var(--accent); }
</style>

<div class="search-page">
    <form class="search-form" method="get" action="/myweb/search.php">
        <input type="text" name="q" placeholder="搜索文章、资料..." value="<?= htmlspecialchars($q) ?>" autofocus>
        <button type="submit">搜索</button>
    </form>

    <?php if ($q): ?>
    <p class="search-stats"><?= $searchStats ?></p>
    <?php endif; ?>

    <?php if ($q): ?>
    <div class="search-ai-bar">
        <button id="aiSearchBtn" class="btn btn-ai-search" onclick="aiSearch()">
            🤖 AI 搜索增强
        </button>
        <span id="aiSearchStatus" class="search-ai-status" style="display:none"></span>
    </div>
    <div id="aiSearchResults" style="display:none"></div>
    <?php endif; ?>

    <?php if ($q && empty($articles) && empty($pages)): ?>
    <div class="search-empty">
        <div class="icon">🔍</div>
        <p>没有找到相关内容</p>
        <p style="font-size:0.85rem;color:var(--text-muted);margin-top:8px">试试其他关键词</p>
    </div>
    <?php endif; ?>

    <?php if (!empty($articles)): ?>
    <h2 class="search-section-title">📝 文章 <span class="count">(<?= count($articles) ?>)</span></h2>
    <?php foreach ($articles as $a): ?>
    <article class="article-card" style="margin-bottom:16px">
        <div class="article-body">
            <h2><a href="/myweb/article.php?id=<?= $a['id'] ?>"><?= htmlspecialchars($a['title']) ?></a></h2>
            <div class="article-meta">
                <span>作者: <?= htmlspecialchars($a['username']) ?></span>
                <span>分类: <?= htmlspecialchars($a['category_name'] ?? '未分类') ?></span>
                <span><?= date('Y-m-d', strtotime($a['created_at'])) ?></span>
            </div>
            <?php $summary = $a['summary'] ?: mb_substr(strip_tags($a['content']), 0, 200); ?>
            <p><?= htmlspecialchars($summary) ?></p>
            <a href="/myweb/article.php?id=<?= $a['id'] ?>" class="read-more">阅读全文 →</a>
        </div>
    </article>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($pages)): ?>
    <h2 class="search-section-title">📄 资料 <span class="count">(<?= count($pages) ?>)</span></h2>
    <div style="display:grid;gap:12px">
        <?php foreach ($pages as $p): ?>
        <a href="/myweb/page.php?slug=<?= urlencode($p['slug']) ?>" class="page-result" style="display:block;padding:18px 22px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);text-decoration:none;color:var(--text-primary);transition:all .25s ease">
            <strong><?= htmlspecialchars($p['title']) ?></strong>
            <small style="color:var(--text-secondary);display:block;margin-top:4px"><?= date('Y-m-d', strtotime($p['created_at'])) ?></small>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function aiSearch() {
    const q = document.querySelector('input[name="q"]').value;
    if (!q.trim()) return;

    const btn = document.getElementById('aiSearchBtn');
    const status = document.getElementById('aiSearchStatus');
    const results = document.getElementById('aiSearchResults');
    btn.disabled = true;
    btn.textContent = '⏳ AI 分析中…';
    status.style.display = 'inline';
    status.textContent = '正在理解搜索意图…';
    results.style.display = 'none';

    fetch('/myweb/ai_search.php?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.textContent = '🤖 AI 搜索增强';
            if (res.success && res.data) {
                const d = res.data;
                let html = '';

                // 展示扩展关键词
                if (d.keywords && d.keywords.length > 0) {
                    html += '<div class="ai-keywords">AI 理解的关键词：';
                    d.keywords.forEach(kw => {
                        html += '<span class="ai-keyword">' + kw + '</span>';
                    });
                    html += '</div>';
                }

                const total = (d.articles || []).length + (d.pages || []).length;
                status.textContent = 'AI 搜索完成，共找到 ' + total + ' 条相关结果';
                status.style.color = 'var(--success)';

                // 文章
                if (d.articles && d.articles.length > 0) {
                    html += '<h2 class="search-section-title">📝 文章 <span class="count">(' + d.articles.length + ')</span><span class="ai-search-badge">AI 排序</span></h2>';
                    d.articles.forEach(function(a) {
                        html += '<article class="article-card" style="margin-bottom:16px">';
                        html += '<div class="article-body">';
                        html += '<h2><a href="/myweb/article.php?id=' + a.id + '">' + a.title + '</a></h2>';
                        html += '<div class="article-meta">';
                        html += '<span>作者: ' + a.username + '</span>';
                        html += '<span>分类: ' + a.category_name + '</span>';
                        html += '<span>' + a.created_at.substr(0, 10) + '</span>';
                        html += '</div>';
                        html += '<p>' + a.summary + '</p>';
                        html += '<a href="/myweb/article.php?id=' + a.id + '" class="read-more">阅读全文 →</a>';
                        html += '</div></article>';
                    });
                }

                // 资料
                if (d.pages && d.pages.length > 0) {
                    html += '<h2 class="search-section-title">📄 资料 <span class="count">(' + d.pages.length + ')</span></h2>';
                    html += '<div style="display:grid;gap:12px">';
                    d.pages.forEach(function(p) {
                        html += '<a href="/myweb/page.php?slug=' + encodeURIComponent(p.slug) + '" class="page-result" style="display:block;padding:18px 22px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);text-decoration:none;color:var(--text-primary);transition:all .25s ease">';
                        html += '<strong>' + p.title + '</strong>';
                        html += '<small style="color:var(--text-secondary);display:block;margin-top:4px">' + (p.created_at || '').substr(0, 10) + '</small>';
                        html += '</a>';
                    });
                    html += '</div>';
                }

                if (!total) {
                    html += '<div class="search-empty"><div class="icon">🔍</div><p>AI 搜索也未找到相关内容</p></div>';
                }

                results.innerHTML = html;
                results.style.display = 'block';
                results.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                status.textContent = 'AI 搜索暂不可用: ' + (res.error || '未知错误');
                status.style.color = 'var(--danger)';
            }
        })
        .catch(function(err) {
            btn.disabled = false;
            btn.textContent = '🤖 AI 搜索增强';
            status.textContent = '网络错误，请重试';
            status.style.color = 'var(--danger)';
        });
}
</script>
<?php require_once 'includes/footer.php'; ?>
