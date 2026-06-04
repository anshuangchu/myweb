// ============================================================
// myweb Design System v2.0 — Client Scripts
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

// ===== 文章自动排版 =====
function runAutoFormat() {
    const article = document.querySelector('.article-content');
    if (!article) return;
    const btn = document.getElementById('btnAutoFormat');
    if (btn) { btn.textContent = '排版中...'; btn.disabled = true; }

    const rawHTML = article.innerHTML;

    // 判断是否需要全文重构（<br>密集文章）
    const brCount = (rawHTML.match(/<br\s*\/?>/gi) || []).length;

    if (brCount >= 3) {
        // 全文重构
        article.innerHTML = formatFullArticle(rawHTML);
        if (btn) { btn.textContent = '✓ 排版完成'; btn.disabled = false; }
        setTimeout(() => { if (btn) btn.textContent = '自动排版'; }, 2000);
        return;
    }

    // 标准HTML增强
    let c = enhanceArticle(article);
    if (btn) {
        btn.textContent = c > 0 ? '✓ 已优化 ' + c + ' 处' : '✓ 已是最佳格式';
        btn.disabled = false;
        setTimeout(() => { if (btn) btn.textContent = '自动排版'; }, 2000);
    }
}

function formatFullArticle(html) {
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    const lines = tmp.textContent.split('\n').map(l => l.trim());
    const blocks = [];

    let i = 0;
    while (i < lines.length) {
        const l = lines[i];
        if (!l) { i++; continue; }

        // 标题
        if (/^第[一二三四五六七八九十\d]+[阶段章节部]/.test(l) || /^[一二三四五六七八九十]+[、．.]/.test(l)) {
            blocks.push({ t: 'h2', c: l.replace(/^[一二三四五六七八九十]+[、．.\s]+/, '') }); i++; continue;
        }
        if (/^\d+\.\d+[\.\s]/.test(l) || /^[（(]\d+[）)]/.test(l)) {
            blocks.push({ t: 'h3', c: l }); i++; continue;
        }
        // 警告
        if (/^⚠/.test(l)) {
            blocks.push({ t: 'warn', c: l.replace(/^⚠️?\s*/, '') }); i++; continue;
        }
        // 代码
        if (isCode(l)) {
            const code = [l]; i++;
            while (i < lines.length && (isCode(lines[i]) || (lines[i] === '' && i+1 < lines.length && isCode(lines[i+1])))) {
                if (lines[i]) code.push(lines[i]);
                i++;
            }
            blocks.push({ t: 'code', c: code.join('\n') });
            continue;
        }
        // 文本段
        const txt = [l]; i++;
        while (i < lines.length && lines[i] && !/^第[一二三四五六七八九十\d]+[阶段章节部]/.test(lines[i]) && !/^[一二三四五六七八九十]+[、．.]/.test(lines[i]) && !/^\d+\.\d+[\.\s]/.test(lines[i]) && !/^⚠/.test(lines[i]) && !isCode(lines[i])) {
            txt.push(lines[i]); i++;
        }
        blocks.push({ t: 'text', c: txt.join('\n') });
    }

    // 渲染
    let out = '';
    for (const b of blocks) {
        switch (b.t) {
            case 'h2': out += '<h2>' + esc(b.c) + '</h2>'; break;
            case 'h3': out += '<h3>' + esc(b.c) + '</h3>'; break;
            case 'code': out += '<pre data-lang="' + lang(b.c) + '"><code>' + esc(b.c) + '</code></pre>'; break;
            case 'warn': out += '<p class="article-warn">' + esc(b.c) + '</p>'; break;
            default:
                let t = esc(b.c);
                t = t.replace(/`([^`]+)`/g, '<code>$1</code>');
                t = t.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
                t = t.replace(/(https?:\/\/[^\s<>"]+)/g, '<a href="$1" target="_blank">$1</a>');
                out += '<p>' + t + '</p>';
        }
    }
    return out;
}

function enhanceArticle(article) {
    let c = 0;
    article.querySelectorAll('pre').forEach(pre => {
        if (!pre.hasAttribute('data-lang')) { pre.setAttribute('data-lang', lang(pre.textContent.slice(0,300))); c++; }
    });
    article.querySelectorAll('p:not(.article-warn)').forEach(p => {
        if (p.querySelector('br')) return;
        const orig = p.innerHTML;
        p.innerHTML = p.innerHTML
            .replace(/`([^`]+)`/g, '<code>$1</code>')
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/(https?:\/\/[^\s<>"]+)/g, '<a href="$1" target="_blank">$1</a>');
        if (orig !== p.innerHTML) c++;
    });
    return c;
}

function isCode(l) {
    if (!l || l.length > 250) return false;
    if (/^(Windows|macOS|Linux)(\s|$)/i.test(l) && l.length < 30) return false;
    if (/^(注意|提示|推荐|安装方式|方式|第[一二三四五]步|权限|验证)/.test(l)) return false;
    return /^(npm |git |curl |wget |sudo |apt |brew |pip |node |yarn |npx |export |set |unset |source |\.\/|mkdir |cd |ls |cp |mv |rm |cat |echo |chmod |chown |docker |kubectl |go |rustc |python |php |java |gcc |make |nvm |claude |code |dir |copy |del |type |where |cls |reg |tasklist |systemctl |scp |ssh )/i.test(l)
        || /^\$env:/i.test(l)
        || /^\$[\s(]/.test(l)
        || /^\$\w+\s*=/.test(l)
        || /^[a-zA-Z_]\w*\s*[:=]\s*["']/.test(l)
        || /^#\s/.test(l)
        || /^\s*(import |from |def |class |const |let |var |function |return |if |for |while )/.test(l);
}

function lang(t) {
    t = t.slice(0, 300);
    if (/\b(npm |git |curl |sudo |apt |brew |export |source |chmod |make |\.\/)/.test(t)) return 'BASH';
    if (/^\$env:/m.test(t)) return 'PowerShell';
    if (/\b(set |dir |copy |del |mkdir |cd |echo |cls |type |reg )/i.test(t)) return 'CMD';
    if (/\b(def |import |print\(|class |elif |except )/.test(t)) return 'Python';
    if (/\b(const |let |var |function |=>|console\.log)/.test(t)) return 'JavaScript';
    if (/\b(SELECT |INSERT |UPDATE |DELETE |CREATE TABLE)/i.test(t)) return 'SQL';
    return 'CODE';
}

function esc(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// ===== AI 排版 =====
function runAiFormat() {
    const article = document.querySelector('.article-content');
    if (!article) return;
    const btn = document.getElementById('btnAiFormat');
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (!csrfMeta) return;

    if (btn) { btn.textContent = 'AI 分析中...'; btn.disabled = true; }

    const content = article.innerHTML;
    const title = document.querySelector('.article-header h1')?.textContent || '';

    fetch('/myweb/ai_format.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'content=' + encodeURIComponent(content) + '&title=' + encodeURIComponent(title) + '&csrf_token=' + encodeURIComponent(csrfMeta.content)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success && res.html) {
            article.innerHTML = res.html;
            if (btn) { btn.textContent = '✓ AI 排版完成'; btn.style.background = 'var(--success)'; btn.style.color = '#fff'; }
        } else {
            if (btn) { btn.textContent = 'AI 排版失败'; }
            showToast(res.error || 'AI 服务异常', 'error');
        }
    })
    .catch(() => {
        if (btn) { btn.textContent = 'AI 排版失败'; }
        showToast('网络错误，请重试', 'error');
    })
    .finally(() => {
        setTimeout(() => {
            if (btn) { btn.textContent = 'AI 排版'; btn.disabled = false; btn.style.background = ''; btn.style.color = ''; }
        }, 2500);
    });
}

// 页面加载时自动执行一次
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.article-content')) runAutoFormat();
});

// ===== 聚焦轮播 =====
(function initCarousel() {
    const carousel = document.getElementById('featuredCarousel');
    if (!carousel) return;
    const slides = carousel.querySelectorAll('.carousel-slide');
    const dots = carousel.querySelectorAll('.carousel-dot');
    if (slides.length < 2) return;

    let current = 0;
    let timer;

    const sidebarItems = carousel.querySelectorAll('.carousel-sidebar-item');

    function goTo(idx) {
        slides[current].classList.remove('active');
        dots[current].classList.remove('active');
        if (sidebarItems[current]) sidebarItems[current].classList.remove('active');
        current = (idx + slides.length) % slides.length;
        slides[current].classList.add('active');
        dots[current].classList.add('active');
        if (sidebarItems[current]) sidebarItems[current].classList.add('active');
    }

    function nextSlide() { goTo(current + 1); }
    function prevSlide() { goTo(current - 1); }

    function startAuto() { timer = setInterval(nextSlide, 5000); }
    function stopAuto() { clearInterval(timer); }

    dots.forEach(d => d.addEventListener('click', () => { stopAuto(); goTo(parseInt(d.dataset.index)); startAuto(); }));
    // 右侧预览列表点击切换
    carousel.querySelectorAll('.carousel-sidebar-item').forEach(item => {
        item.addEventListener('click', (e) => { e.preventDefault(); stopAuto(); goTo(parseInt(item.dataset.index)); startAuto(); });
    });
    carousel.addEventListener('mouseenter', stopAuto);
    carousel.addEventListener('mouseleave', startAuto);

    // 触摸滑动
    let touchX = 0;
    carousel.addEventListener('touchstart', e => { touchX = e.touches[0].clientX; stopAuto(); }, { passive: true });
    carousel.addEventListener('touchend', e => {
        const dx = e.changedTouches[0].clientX - touchX;
        if (Math.abs(dx) > 40) dx > 0 ? prevSlide() : nextSlide();
        startAuto();
    });

    startAuto();
})();

// ===== 移动端导航面板切换 =====
const navToggle = document.getElementById('navToggle');
const mobileNav = document.getElementById('mobileNav');

if (navToggle && mobileNav) {
    navToggle.addEventListener('click', () => {
        const isOpen = mobileNav.classList.toggle('open');
        navToggle.setAttribute('aria-expanded', isOpen);
        document.body.style.overflow = isOpen ? 'hidden' : '';
    });

    // 点击导航链接后自动关闭
    mobileNav.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            mobileNav.classList.remove('open');
            navToggle.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        });
    });
}

// ESC 关闭
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && mobileNav && mobileNav.classList.contains('open')) {
        mobileNav.classList.remove('open');
        navToggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }
});

// ===== 卡片入场动画 =====
document.querySelectorAll('.article-card').forEach((card, i) => {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.classList.add('card-visible');
                }, i * 60);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.05, rootMargin: '50px' });
    observer.observe(card);
});

// ===== 卡片鼠标追踪光晕 =====
document.querySelectorAll('.article-card, .stat-card').forEach(card => {
    card.addEventListener('mousemove', (e) => {
        const rect = card.getBoundingClientRect();
        card.style.setProperty('--mx', (e.clientX - rect.left) + 'px');
        card.style.setProperty('--my', (e.clientY - rect.top) + 'px');
    });
});

// ===== 图片懒加载模糊淡入 =====
(function initLazyImages() {
    const imgs = document.querySelectorAll('img.lazy-load');
    if (!imgs.length) return;
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            const img = entry.target;
            if (img.dataset.src) img.src = img.dataset.src;
            img.onload = () => img.classList.add('lazy-loaded');
            if (img.complete) img.classList.add('lazy-loaded');
            observer.unobserve(img);
        });
    }, { rootMargin: '200px' });
    imgs.forEach(img => observer.observe(img));
})();

}); // DOMContentLoaded

// ===== AI 对话助手 =====

function initAiChatDrag() {
    const widget = document.getElementById('aiChatWidget');
    const header = widget?.querySelector('.ai-chat-header');
    if (!widget || !header) return;

    let isDragging = false, startX, startY, origLeft, origTop;

    function onStart(e) {
        const ev = e.touches ? e.touches[0] : e;
        if (ev.target.closest('.ai-chat-close')) return;
        isDragging = true;
        const rect = widget.getBoundingClientRect();
        const curLeft = parseFloat(widget.style.left) || rect.left;
        const curTop  = parseFloat(widget.style.top)  || rect.top;
        widget.style.left = curLeft + 'px';
        widget.style.top  = curTop + 'px';
        widget.style.right = 'auto';
        startX = ev.clientX; startY = ev.clientY;
        origLeft = curLeft; origTop = curTop;
        widget.classList.add('ai-chat-dragging');
        document.body.style.userSelect = 'none';
    }

    function onMove(e) {
        if (!isDragging) return;
        const ev = e.touches ? e.touches[0] : e;
        widget.style.left = (origLeft + ev.clientX - startX) + 'px';
        widget.style.top  = (origTop  + ev.clientY - startY) + 'px';
    }

    function onEnd() {
        if (!isDragging) return;
        isDragging = false;
        widget.classList.remove('ai-chat-dragging');
        document.body.style.userSelect = '';
    }

    header.addEventListener('mousedown', onStart);
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onEnd);
    header.addEventListener('touchstart', onStart, { passive: true });
    document.addEventListener('touchmove', onMove, { passive: true });
    document.addEventListener('touchend', onEnd);
}

function toggleChat() {
    const panel = document.getElementById('aiChatPanel');
    const icon = document.getElementById('chatToggleIcon');
    if (panel.style.display === 'none' || panel.style.display === '') {
        panel.style.display = 'flex';
        panel.style.flexDirection = 'column';
        icon.textContent = '✕';
        document.getElementById('aiChatInput').focus();
    } else {
        panel.style.display = 'none';
        icon.textContent = '💬';
    }
}

function sendChat() {
    const input = document.getElementById('aiChatInput');
    const question = input.value.trim();
    if (!question) return;

    const body = document.getElementById('aiChatBody');
    const loading = document.getElementById('aiChatLoading');

    const userDiv = document.createElement('div');
    userDiv.className = 'ai-chat-msg ai-chat-msg-user';
    userDiv.innerHTML = '<div class="ai-chat-msg-content">' + escapeHtml(question) + '</div>';
    body.appendChild(userDiv);
    body.scrollTop = body.scrollHeight;

    input.value = '';
    loading.style.display = 'flex';

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    fetch('/myweb/ai_chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'question=' + encodeURIComponent(question) + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(r => r.json())
    .then(res => {
        loading.style.display = 'none';
        const botDiv = document.createElement('div');
        botDiv.className = 'ai-chat-msg ai-chat-msg-bot';
        if (res.success) {
            botDiv.innerHTML = '<div class="ai-chat-msg-content">' + escapeHtml(res.data.answer) + '</div>';
        } else {
            botDiv.innerHTML = '<div class="ai-chat-msg-content" style="color:var(--danger)">⚠️ ' + escapeHtml(res.error || '请求失败') + '</div>';
        }
        body.appendChild(botDiv);
        body.scrollTop = body.scrollHeight;
    })
    .catch(() => {
        loading.style.display = 'none';
        const botDiv = document.createElement('div');
        botDiv.className = 'ai-chat-msg ai-chat-msg-bot';
        botDiv.innerHTML = '<div class="ai-chat-msg-content" style="color:var(--danger)">⚠️ 网络错误，请重试</div>';
        body.appendChild(botDiv);
        body.scrollTop = body.scrollHeight;
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ===== Toast 通知 =====
const TOAST_ICONS = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
function showToast(message, type = 'info') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.innerHTML = '<span class="toast-icon">' + (TOAST_ICONS[type] || 'ℹ️') + '</span><span class="toast-msg">' + escapeHtml(message) + '</span>';
    container.appendChild(toast);
    setTimeout(() => { if (toast.parentNode) toast.remove(); }, 3100);
}

// ===== 返回顶部按钮 =====
(function initBackToTop() {
    const btn = document.createElement('button');
    btn.className = 'back-to-top';
    btn.innerHTML = '↑';
    btn.title = '返回顶部';
    btn.setAttribute('aria-label', '返回顶部');
    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    document.body.appendChild(btn);

    let ticking = false;
    window.addEventListener('scroll', () => {
        if (!ticking) {
            requestAnimationFrame(() => {
                btn.classList.toggle('visible', window.scrollY > 400);
                ticking = false;
            });
            ticking = true;
        }
    }, { passive: true });
})();

// AI 拖拽初始化
document.addEventListener('DOMContentLoaded', () => initAiChatDrag());
