// ============================================================
// myweb Design System v2.0 — Client Scripts
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

// ===== 代码块自动识别与格式化 =====
(function autoFormatCode() {
    const article = document.querySelector('.article-content');
    if (!article) return;

    // 已有 pre 标签的，直接识别语言
    article.querySelectorAll('pre').forEach(pre => {
        if (pre.hasAttribute('data-lang')) return;
        const text = pre.textContent.slice(0, 200);
        pre.setAttribute('data-lang', detectLang(text));
    });

    // 处理纯文本中的代码行（<br>分隔的代码块）
    const paras = article.querySelectorAll('p');
    paras.forEach(p => {
        const html = p.innerHTML;
        // 如果段落包含大量 <br> 且含代码特征
        if (!html.includes('<br') && !html.includes('<br/>')) return;

        const lines = html.split(/<br\s*\/?>/i);
        if (lines.length < 3) return;

        const coded = lines.map(l => ({ text: l.trim(), isCode: isCodeLine(l) }));

        // 查找连续 >=2 行的代码块
        let fragments = [];
        let buf = [];
        let bufIsCode = null;

        for (const item of coded) {
            if (bufIsCode === null) {
                bufIsCode = item.isCode;
                buf.push(item.text);
            } else if (item.isCode === bufIsCode) {
                buf.push(item.text);
            } else {
                fragments.push({ isCode: bufIsCode, lines: buf });
                buf = [item.text];
                bufIsCode = item.isCode;
            }
        }
        if (buf.length) fragments.push({ isCode: bufIsCode, lines: buf });

        // 只处理有 >=2 行代码的块
        const hasCodeBlock = fragments.some(f => f.isCode && f.lines.length >= 2);
        if (!hasCodeBlock) return;

        // 重建 HTML
        let out = '';
        for (const frag of fragments) {
            if (frag.isCode && frag.lines.length >= 2) {
                const codeText = frag.lines.join('\n');
                const lang = detectLang(codeText);
                out += '<pre data-lang="' + lang + '"><code>' + escHtml(codeText) + '</code></pre>';
            } else {
                out += '<p>' + frag.lines.join('<br>') + '</p>';
            }
        }
        p.outerHTML = out;
    });

    function isCodeLine(line) {
        const t = line.trim();
        if (!t) return false;
        // 排除纯文字标题行
        if (/^(Windows|macOS|Linux|Ubuntu|CentOS)(\s|$)/i.test(t) && t.length < 30) return false;
        if (/^(Windows|Mac|Linux).*(PowerShell|CMD|命令|终端|Bash|Zsh|用户)/i.test(t)) return false;

        return /^(npm |git |curl |wget |sudo |apt |brew |pip |node |yarn |npx |export |set |unset |source |\.\/|mkdir |cd |ls |cp |mv |rm |cat |echo |chmod |chown |docker |kubectl |go |rustc |python |php |java |gcc |make |nvm |claude |code |dir |copy |del |type |where |cls |reg |tasklist |systemctl |scp |ssh )/i.test(t)
            || /^\$env:/i.test(t)
            || /^\$[\s(]/.test(t)
            || /^>\s/.test(t)
            || /^#\s(?!.*配置|.*阶段|.*注意|.*推荐|.*用户)/.test(t)
            || /^[a-zA-Z0-9._-]+[>$]\s/.test(t)
            || /^\$\w+\s*=/.test(t)
            || /^\w+\s*[:=]\s*["']/.test(t)
            || /^[{}[\]]\s*$/.test(t);
    }

    function detectLang(text) {
        const t = text.slice(0, 300);
        if (/\b(npm |git |curl |sudo |apt |brew |\.\/|export |source |chmod |nvm )/.test(t)) return 'BASH';
        if (/^\$env:/m.test(t) || /\$\w+\s*=|Get-\w+|Write-\w+/.test(t)) return 'PowerShell';
        if (/\b(set |dir |copy |del |mkdir |cd |echo |cls |type )/i.test(t)) return 'CMD';
        if (/\b(def |import |print\(|from \w+ import|class \w+.*:)/.test(t)) return 'Python';
        if (/\b(const |let |var |function\s+\w+\s*\(|=>|console\.log)/.test(t)) return 'JavaScript';
        if (/\b(SELECT |INSERT |UPDATE |DELETE |CREATE TABLE)/i.test(t)) return 'SQL';
        if (/[\w-]+\s*:\s*[\w#]+;|@media/.test(t)) return 'CSS';
        return 'CODE';
    }

    function escHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
})();

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
