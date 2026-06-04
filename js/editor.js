// ============================================================
// 内容编辑器工具栏（page_edit.php 用 — 动态构建工具栏）
// ============================================================
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
                    t = t.replace(/\n{3,}/g, '\n\n');
                    t = t.replace(/[ \t]+$/gm, '');
                    t = t.replace(/\n{2,}(<\/[a-z]+>)/g, '\n$1');
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


// ============================================================
// 文章编辑器工具栏 + 标签管理（article_edit.php 用 — 静态工具栏）
// ============================================================
(function() {
    const ta = document.getElementById('editor');
    if (!ta) return;

    // ===== 格式化工具 =====

    function wrap(tag) {
        const s = ta.selectionStart, e = ta.selectionEnd;
        const sel = ta.value.substring(s, e) || tag.toUpperCase();
        const html = '<' + tag + '>' + sel + '</' + tag + '>';
        ta.value = ta.value.substring(0, s) + html + ta.value.substring(e);
        ta.focus();
        ta.setSelectionRange(s + tag.length + 2, s + tag.length + 2 + sel.length);
    }

    function insertBlock(type) {
        const s = ta.selectionStart, e = ta.selectionEnd;
        let sel = ta.value.substring(s, e) || '内容';
        let html;
        if (type === 'pre') html = '\n<pre><code>' + sel + '</code></pre>\n';
        else if (type === 'blockquote') html = '\n<blockquote><p>' + sel + '</p></blockquote>\n';
        ta.value = ta.value.substring(0, s) + html + ta.value.substring(e);
        ta.focus();
    }

    function insertWarn() {
        const s = ta.selectionStart, e = ta.selectionEnd;
        const sel = ta.value.substring(s, e) || '注意事项';
        ta.value = ta.value.substring(0, s) + '\n<p class="article-warn">' + sel + '</p>\n' + ta.value.substring(e);
        ta.focus();
    }

    function insertList(type) {
        const s = ta.selectionStart;
        const tag = type === 'ol' ? 'ol' : 'ul';
        const html = '\n<' + tag + '>\n  <li>项目一</li>\n  <li>项目二</li>\n  <li>项目三</li>\n</' + tag + '>\n';
        ta.value = ta.value.substring(0, s) + html + ta.value.substring(s);
        ta.focus();
    }

    function insertLink() {
        const url = prompt('链接地址:', 'https://');
        if (!url) return;
        const s = ta.selectionStart, e = ta.selectionEnd;
        const sel = ta.value.substring(s, e) || url;
        ta.value = ta.value.substring(0, s) + '<a href="' + url + '" target="_blank">' + sel + '</a>' + ta.value.substring(e);
        ta.focus();
    }

    function insertImg() {
        const url = prompt('图片地址:', 'https://');
        if (!url) return;
        const s = ta.selectionStart;
        ta.value = ta.value.substring(0, s) + '<img src="' + url + '" alt="" style="max-width:100%">\n' + ta.value.substring(s);
        ta.focus();
    }

    function insertHR() {
        const s = ta.selectionStart;
        ta.value = ta.value.substring(0, s) + '\n<hr>\n' + ta.value.substring(s);
        ta.focus();
    }

    // ===== 标签管理 =====

    function getTags() {
        return [...new Set(document.getElementById('tagInput').value.split(/[,\s]+/).map(t => t.trim()).filter(t => t))];
    }

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function renderTags() {
        var ts = getTags();
        document.getElementById('tagsChips').innerHTML = ts.map(function(t) {
            return '<span class="tag tag-selected" onclick="rmTag(\'' + escHtml(t).replace(/'/g, '&#39;') + '\')">' + escHtml(t) + ' ✕</span>';
        }).join('');
        document.getElementById('tagsHidden').innerHTML = ts.map(function(t) {
            return '<input type="hidden" name="tags[]" value="' + escHtml(t).replace(/"/g, '&quot;') + '" form="articleForm">';
        }).join('');
    }

    window.addTag = function(n) {
        var i = document.getElementById('tagInput'), ts = getTags();
        if (!ts.includes(n)) { ts.push(n); i.value = ts.join(', '); renderTags(); }
    };

    window.rmTag = function(n) {
        var i = document.getElementById('tagInput');
        i.value = getTags().filter(function(t) { return t !== n; }).join(', ');
        renderTags();
    };

    // ===== 键盘快捷键 =====

    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); }
        if ((e.ctrlKey || e.metaKey) && e.key === 'b') { e.preventDefault(); wrap('strong'); }
        if ((e.ctrlKey || e.metaKey) && e.key === 'i') { e.preventDefault(); wrap('em'); }
    });

    // ===== 初始化 =====

    document.addEventListener('DOMContentLoaded', function() {
        var tagInput = document.getElementById('tagInput');
        if (tagInput) {
            renderTags();
            tagInput.addEventListener('input', renderTags);
        }
    });

    // 暴露给 HTML onclick 属性
    window.wrap = wrap;
    window.insertBlock = insertBlock;
    window.insertWarn = insertWarn;
    window.insertList = insertList;
    window.insertLink = insertLink;
    window.insertImg = insertImg;
    window.insertHR = insertHR;
})();
