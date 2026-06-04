// 页面加载动画
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.article-card').forEach((card, i) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.4s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, i * 100);
    });
});

// 控制台快速切标签
document.querySelectorAll('.admin-sidebar a')?.forEach(link => {
    if (link.href === window.location.href) {
        link.classList.add('active');
    }
});

// 内容编辑器工具栏
(function() {
    const textarea = document.getElementById('content');
    if (!textarea) return;

    const toolbar = document.createElement('div');
    toolbar.className = 'editor-toolbar';
    toolbar.style.cssText = `
        display: flex; gap: 4px; padding: 8px; background: var(--bg-hover);
        border: 1px solid var(--border); border-bottom: none; border-radius: 6px 6px 0 0;
        flex-wrap: wrap;
    `;

    const buttons = [
        { label: 'H2', cmd: 'h2' },
        { label: 'H3', cmd: 'h3' },
        { label: 'B', cmd: 'bold' },
        { label: 'I', cmd: 'italic' },
        { label: '「」', cmd: 'quote' },
        { label: '•', cmd: 'list' },
        { label: '🔗', cmd: 'link' },
        { label: '🖼️', cmd: 'image' },
        { label: '```', cmd: 'code' },
        { label: '¶', cmd: 'clean' },
    ];

    buttons.forEach(b => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = b.label;
        btn.style.cssText = `
            padding: 4px 10px; border: 1px solid var(--border); border-radius: 4px;
            background: var(--bg-card); color: var(--text-primary); cursor: pointer;
            font-size: 0.85rem; transition: all .15s;
        `;
        btn.onmouseover = () => btn.style.background = 'var(--bg-input)';
        btn.onmouseout = () => btn.style.background = 'var(--bg-card)';
        btn.onclick = () => {
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const sel = textarea.value.substring(start, end);
            let insert = '';

            switch (b.cmd) {
                case 'h2': insert = `\n## ${sel || '标题'}\n`; break;
                case 'h3': insert = `\n### ${sel || '标题'}\n`; break;
                case 'bold': insert = `<strong>${sel || '粗体'}</strong>`; break;
                case 'italic': insert = `<em>${sel || '斜体'}</em>`; break;
                case 'quote': insert = `\n<blockquote>${sel || '引用文字'}</blockquote>\n`; break;
                case 'list': insert = `\n<ul>\n  <li>${sel || '项目'}</li>\n</ul>\n`; break;
                case 'link': insert = `<a href="${sel ? '' : 'https://'}">${sel || '链接文字'}</a>`; break;
                case 'image': insert = `<img src="${sel ? '' : '图片地址'}" alt="">`; break;
                case 'code': insert = `\n<pre><code>${sel || '代码'}</code></pre>\n`; break;
                case 'clean':
                    let t = textarea.value;
                    t = t.replace(/\n{3,}/g, '\n\n');          // 去多余空行
                    t = t.replace(/[ \t]+$/gm, '');            // 去行尾空格
                    t = t.replace(/\n{2,}(<\/[a-z]+>)/g, '\n$1'); // 标签前不多空行
                    textarea.value = t;
                    return;
            }

            textarea.setRangeText(insert, start, end, 'end');
            textarea.focus();
        };
        toolbar.appendChild(btn);
    });

    textarea.parentNode.insertBefore(toolbar, textarea);
    textarea.style.cssText = `
        border-radius: 0 0 6px 6px !important;
        font-family: inherit !important;
    `;
})();
