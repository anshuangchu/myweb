/**
 * search.php — AI 搜索增强 v3.2（glow-card 输出）
 */
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('aiSearchBtn');
    if (!btn) return;
    btn.addEventListener('click', aiSearch);
});

function aiSearch() {
    var q = document.querySelector('.search-form-lg input[name="q"]');
    if (!q || !q.value.trim()) return;

    var btn = document.getElementById('aiSearchBtn');
    var status = document.getElementById('aiSearchStatus');
    var results = document.getElementById('aiSearchResults');
    btn.disabled = true;
    btn.innerHTML = '⏳ 分析中…';
    status.classList.remove('hidden');
    status.textContent = 'AI 正在理解搜索意图…';
    results.classList.add('hidden');

    fetch('/myweb/ai_search.php?q=' + encodeURIComponent(q.value.trim()))
        .then(function(r) { return r.json(); })
        .then(function(res) {
            btn.disabled = false;
            btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M12 2a4 4 0 0 1 4 4v2a4 4 0 0 1-8 0V6a4 4 0 0 1 4-4z"/><path d="M8 14v-2a4 4 0 0 1 8 0v2"/><circle cx="12" cy="12" r="10"/></svg> AI 增强';
            if (!res.success || !res.data) { status.textContent = 'AI 服务暂不可用'; status.style.color = 'var(--danger)'; return; }
            var d = res.data, html = '';
            if (d.keywords && d.keywords.length) {
                html += '<div class="ai-keywords">';
                d.keywords.forEach(function(k) { html += '<span class="ai-keyword">' + esc(k) + '</span>'; });
                html += '</div>';
            }
            var total = (d.articles || []).length + (d.pages || []).length;
            status.textContent = 'AI 搜索完成，共 ' + total + ' 条结果'; status.style.color = 'var(--success)';
            if (d.articles && d.articles.length) {
                html += '<div class="search-section-head"><h3>文章</h3><span class="count">' + d.articles.length + ' 篇</span><span class="ai-search-badge">AI 排序</span></div><div class="glow-grid">';
                d.articles.forEach(function(a) {
                    html += '<a href="/myweb/article.php?id=' + a.id + '" class="glow-card">';
                    if (a.cover_image) html += '<div class="glow-card-cover"><img src="/myweb/' + esc(a.cover_image) + '" alt="" loading="lazy"></div>';
                    html += '<div class="glow-card-body"><h3>' + esc(a.title) + '</h3><p>' + esc(a.summary || '') + '</p><div class="glow-card-meta"><span>' + esc(a.username || '') + '</span><span class="meta-dot">·</span><span>' + (a.created_at || '').substr(0, 10) + '</span>';
                    if (a.category_name) html += '<span class="meta-dot">·</span><span class="glow-card-tag">' + esc(a.category_name) + '</span>';
                    html += '</div></div></a>';
                });
                html += '</div>';
            }
            if (d.pages && d.pages.length) {
                html += '<div class="search-section-head"><h3>资料</h3><span class="count">' + d.pages.length + ' 篇</span></div><div class="glow-grid glow-grid-sm">';
                d.pages.forEach(function(p) {
                    html += '<a href="/myweb/page.php?slug=' + encodeURIComponent(p.slug || '') + '" class="glow-card"><div class="glow-card-cover" style="display:flex;align-items:center;justify-content:center"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="36" height="36" opacity="0.25"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div><div class="glow-card-body"><h3>' + esc(p.title) + '</h3><div class="glow-card-meta"><span>' + (p.created_at || '').substr(0, 10) + '</span></div></div></a>';
                });
                html += '</div>';
            }
            if (!total) html += '<div class="search-empty-state"><div class="search-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40" height="40"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div><h3>AI 也未找到相关内容</h3></div>';
            results.innerHTML = html; results.classList.remove('hidden');
        }).catch(function() { btn.disabled = false; btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M12 2a4 4 0 0 1 4 4v2a4 4 0 0 1-8 0V6a4 4 0 0 1 4-4z"/><path d="M8 14v-2a4 4 0 0 1 8 0v2"/><circle cx="12" cy="12" r="10"/></svg> AI 增强'; status.textContent = '网络错误'; status.style.color = 'var(--danger)'; });
}

function esc(s) { var d = document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML; }
