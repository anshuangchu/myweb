<?php
require_once '../includes/header.php';
if (!hasRole('super_admin', 'admin', 'editor')) redirect('/myweb/login.php?redirect=/myweb/admin/article_edit.php');

$id = $_GET['id'] ?? 0;
$article = ['title'=>'', 'content'=>'', 'summary'=>'', 'category_id'=>0, 'cover_image'=>'', 'status'=>'draft'];
$articleTags = [];

if ($id) {
    $stmt = db()->prepare("SELECT * FROM articles WHERE id = ?");
    $stmt->execute([$id]);
    $article = $stmt->fetch();
    if (!$article) redirect('/myweb/admin/articles.php');
    $stmt = db()->prepare("SELECT t.id, t.name FROM tags t JOIN article_tags at ON t.id = at.tag_id WHERE at.article_id = ? ORDER BY t.name");
    $stmt->execute([$id]);
    $articleTags = $stmt->fetchAll();
}

$categories = db()->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$allTags = db()->query("SELECT * FROM tags ORDER BY name")->fetchAll();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $summary = trim($_POST['summary'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $status = $_POST['status'] ?? 'draft';
    $tagNames = $_POST['tags'] ?? [];

    if (!hasRole('super_admin', 'admin') && $status === 'published') {
        $status = 'pending';
    }
    if (!$title) $error = '请输入文章标题';

    // 封面图片上传
    $cover_image = $article['cover_image'];
    if (!empty($_FILES['cover_image']['name'])) {
        if ($_FILES['cover_image']['size'] > 5 * 1024 * 1024) {
            $error = '封面图片不能超过 5MB';
        } else {
            $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['cover_image']['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, $allowedMime)) {
                $error = '封面图片仅支持 JPG/PNG/GIF/WebP 格式';
            } elseif (!getimagesize($_FILES['cover_image']['tmp_name'])) {
                $error = '上传文件不是有效的图片';
            } else {
                $ext = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
                $filename = 'uploads/' . uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['cover_image']['tmp_name'], __DIR__ . '/../' . $filename);
                $cover_image = $filename;
            }
        }
    }

    if (!$error) {
        if ($id) {
            $stmt = db()->prepare("UPDATE articles SET title=?, content=?, summary=?, category_id=?, cover_image=?, status=? WHERE id=?");
            $stmt->execute([$title, $content, $summary, $category_id ?: null, $cover_image, $status, $id]);
        } else {
            $stmt = db()->prepare("INSERT INTO articles (title, content, summary, category_id, cover_image, status, author_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $content, $summary, $category_id ?: null, $cover_image, $status, $_SESSION['user_id']]);
            $id = db()->lastInsertId();
        }

        // 同步标签
        if ($id) {
            db()->prepare("DELETE FROM article_tags WHERE article_id = ?")->execute([$id]);
            if (!empty($tagNames)) {
                $tagNames = array_unique(array_map('trim', $tagNames));
                $tagStmt = db()->prepare("INSERT IGNORE INTO tags (name) VALUES (?)");
                $tagIdStmt = db()->prepare("SELECT id FROM tags WHERE name = ?");
                $atStmt = db()->prepare("INSERT INTO article_tags (article_id, tag_id) VALUES (?, ?)");
                foreach ($tagNames as $name) {
                    if ($name === '') continue;
                    $tagStmt->execute([$name]);
                    $tagIdStmt->execute([$name]);
                    $tagRow = $tagIdStmt->fetch();
                    if ($tagRow) $atStmt->execute([$id, $tagRow['id']]);
                }
            }
        }
        redirect('/myweb/admin/articles.php');
    }
}
?>

<div class="admin-layout">
    <aside class="admin-sidebar"><?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?></aside>
    <main class="admin-main">
        <div class="editor-layout">
            <div class="editor-form">
                <h2><?= $id ? '编辑文章' : '写文章' ?></h2>
                <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

                <form method="post" enctype="multipart/form-data" id="articleForm">
                    <?= csrfField() ?>
                    <div class="form-group">
                        <label>标题</label>
                        <input type="text" name="title" id="articleTitle" required value="<?= htmlspecialchars($article['title']) ?>" placeholder="输入文章标题...">
                    </div>

                    <!-- 格式工具栏 -->
                    <div class="editor-toolbar">
                        <button type="button" class="fmt-btn" onclick="insertTag('h2', '标题')" title="二级标题">H2</button>
                        <button type="button" class="fmt-btn" onclick="insertTag('h3', '子标题')" title="三级标题">H3</button>
                        <button type="button" class="fmt-btn" onclick="insertTag('b', '粗体')" title="粗体"><b>B</b></button>
                        <button type="button" class="fmt-btn" onclick="insertTag('code', '代码')" title="行内代码">&lt;/&gt;</button>
                        <button type="button" class="fmt-btn" onclick="insertBlock('pre')" title="代码块">{ }</button>
                        <button type="button" class="fmt-btn" onclick="insertBlock('blockquote')" title="引用">❝</button>
                        <button type="button" class="fmt-btn" onclick="insertBlock('warn')" title="警告">⚠</button>
                        <button type="button" class="fmt-btn" onclick="insertLink()" title="链接">🔗</button>
                        <span class="fmt-spacer"></span>
                        <button type="button" class="fmt-btn fmt-ai" onclick="aiFormatContent()" title="AI 自动排版">✨ AI排版</button>
                    </div>

                    <div class="form-group">
                        <label>内容</label>
                        <div class="editor-panes">
                            <div class="editor-pane editor-pane-left">
                                <textarea name="content" id="content" rows="20" placeholder="开始写作...支持 HTML 标签"><?= htmlspecialchars($article['content']) ?></textarea>
                            </div>
                            <div class="editor-pane editor-pane-right" id="previewPane">
                                <div class="editor-pane-label">预览</div>
                                <div class="article-content" id="previewContent"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>摘要</label>
                        <textarea name="summary" id="articleSummary" rows="2" placeholder="可选，留空自动取正文前200字"><?= htmlspecialchars($article['summary']) ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>分类</label>
                            <select name="category_id">
                                <option value="">无分类</option>
                                <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $c['id']==$article['category_id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>状态</label>
                            <select name="status">
                                <option value="draft" <?= $article['status']=='draft'?'selected':'' ?>>草稿</option>
                                <option value="pending" <?= $article['status']=='pending'?'selected':'' ?>>待审核</option>
                                <option value="published" <?= $article['status']=='published'?'selected':'' ?>>发布</option>
                                <option value="archived" <?= $article['status']=='archived'?'selected':'' ?>>归档</option>
                            </select>
                            <?php if (!hasRole('super_admin', 'admin')): ?>
                            <small style="color:var(--gray-500);display:block;margin-top:4px">提交后需管理员审核</small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>标签</label>
                        <input type="text" id="tagInput" placeholder="输入标签，空格或逗号分隔" value="<?= htmlspecialchars(implode(', ', array_column($articleTags, 'name'))) ?>">
                        <div id="tagHiddenContainer"></div>
                        <div id="tagChips" class="tag-chips">
                            <?php foreach ($articleTags as $t): ?>
                            <span class="tag tag-selected" data-tag="<?= htmlspecialchars($t['name']) ?>"><?= htmlspecialchars($t['name']) ?> ✕</span>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!empty($allTags)): ?>
                        <div class="tag-suggestions">
                            <small style="color:var(--gray-500)">常用: </small>
                            <?php foreach ($allTags as $t): ?>
                            <span class="tag tag-suggest" data-tag="<?= htmlspecialchars($t['name']) ?>" onclick="addTag('<?= htmlspecialchars($t['name'], ENT_QUOTES) ?>')"><?= htmlspecialchars($t['name']) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>封面图片</label>
                        <input type="file" name="cover_image" accept="image/*">
                        <?php if ($article['cover_image']): ?>
                            <br><img src="/myweb/<?= $article['cover_image'] ?>" style="max-width:200px;margin-top:8px;border-radius:8px">
                        <?php endif; ?>
                    </div>

                    <div class="editor-actions">
                        <button type="submit" class="btn btn-primary">保存</button>
                        <a href="/myweb/admin/articles.php" class="btn">取消</a>
                        <span class="editor-status" id="editorStatus"></span>
                    </div>
                </form>
            </div>

            <!-- AI 侧栏 -->
            <aside class="ai-panel">
                <div class="ai-panel-header"><h3>🤖 AI 助手</h3></div>
                <div class="ai-panel-body">
                    <p class="ai-panel-desc">基于文章内容智能生成</p>
                    <div class="ai-actions">
                        <button class="ai-btn" onclick="aiHelper('summarize')">📝 生成摘要</button>
                        <button class="ai-btn" onclick="aiHelper('polish')">✨ 润色全文</button>
                        <button class="ai-btn" onclick="showExpandDialog()">✍️ 续写内容</button>
                        <div style="border-top:1px solid var(--gray-700);margin:8px 0"></div>
                        <button class="ai-btn" onclick="showGenerateDialog()" style="border-color:var(--accent)">🌱 全文生成</button>
                        <button class="ai-btn" onclick="aiHelper('suggest_title')" style="border-color:var(--purple)">🏆 标题优化</button>
                    </div>
                    <div id="aiLoading" class="ai-loading" style="display:none">
                        <div class="ai-spinner"></div><span>AI 思考中…</span>
                    </div>
                    <div id="aiError" class="ai-error"></div>
                    <div id="aiResult" class="ai-result" style="display:none">
                        <div class="ai-result-header">
                            <span id="aiResultLabel"></span>
                            <button class="ai-insert-btn" onclick="aiInsertResult()">插入</button>
                        </div>
                        <div id="aiResultContent" class="ai-result-content"></div>
                    </div>
                </div>
            </aside>
        </div>
    </main>
</div>

<script>
let aiLastResult = '';
let aiLastAction = '';

// ===== 实时预览 =====
const contentArea = document.getElementById('content');
const previewArea = document.getElementById('previewContent');
function updatePreview() {
    previewArea.innerHTML = contentArea.value;
}
contentArea.addEventListener('input', updatePreview);
updatePreview();

// ===== 格式工具栏 =====
function insertTag(tag, placeholder) {
    const ta = contentArea;
    const sel = ta.value.substring(ta.selectionStart, ta.selectionEnd) || placeholder;
    let open, close;
    switch(tag) {
        case 'h2': open='<h2>'; close='</h2>'; break;
        case 'h3': open='<h3>'; close='</h3>'; break;
        case 'b': open='<strong>'; close='</strong>'; break;
        case 'code': open='<code>'; close='</code>'; break;
        default: open='<'+tag+'>'; close='</'+tag+'>';
    }
    const before = ta.value.substring(0, ta.selectionStart);
    const after = ta.value.substring(ta.selectionEnd);
    ta.value = before + open + sel + close + after;
    ta.focus();
    ta.setSelectionRange(before.length + open.length, before.length + open.length + sel.length);
    updatePreview();
}

function insertBlock(type) {
    const ta = contentArea;
    const sel = ta.value.substring(ta.selectionStart, ta.selectionEnd) || '内容';
    let html;
    switch(type) {
        case 'pre': html = '<pre><code>' + sel + '</code></pre>'; break;
        case 'blockquote': html = '<blockquote><p>' + sel + '</p></blockquote>'; break;
        case 'warn': html = '<p class="article-warn">' + sel + '</p>'; break;
        default: html = sel;
    }
    const before = ta.value.substring(0, ta.selectionStart);
    const after = ta.value.substring(ta.selectionEnd);
    ta.value = before + '\n' + html + '\n' + after;
    ta.focus();
    updatePreview();
}

function insertLink() {
    const url = prompt('输入链接地址:', 'https://');
    if (!url) return;
    const ta = contentArea;
    const sel = ta.value.substring(ta.selectionStart, ta.selectionEnd) || url;
    const html = '<a href="' + url + '" target="_blank">' + sel + '</a>';
    const before = ta.value.substring(0, ta.selectionStart);
    const after = ta.value.substring(ta.selectionEnd);
    ta.value = before + html + after;
    ta.focus();
    updatePreview();
}

// ===== AI 排版 =====
function aiFormatContent() {
    const ta = contentArea;
    if (!ta.value.trim()) return;
    const btn = document.querySelector('.fmt-ai');
    btn.textContent = '⏳'; btn.disabled = true;
    const title = document.getElementById('articleTitle').value;

    fetch('/myweb/ai_format.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'content=' + encodeURIComponent(ta.value) + '&title=' + encodeURIComponent(title) + '&csrf_token=' + document.querySelector('[name=csrf_token]').value
    })
    .then(r => r.json())
    .then(res => {
        if (res.success && res.html) {
            ta.value = res.html;
            updatePreview();
            btn.textContent = '✓'; btn.style.background = 'var(--success)'; btn.style.color = '#fff';
        } else {
            // 本地排版兜底
            localFormatFallback();
            btn.textContent = '✓';
        }
    })
    .catch(() => {
        localFormatFallback();
        btn.textContent = '✓';
    })
    .finally(() => {
        setTimeout(() => { btn.textContent = '✨ AI排版'; btn.disabled = false; btn.style.background = ''; btn.style.color = ''; }, 2000);
    });
}

function localFormatFallback() {
    const ta = document.getElementById('content');
    let html = ta.value;
    if (!html.trim()) return;
    // 简单清洗
    html = html.replace(/\n{3,}/g, '\n\n');
    const blocks = html.split(/\n\n+/);
    const out = [];
    for (const block of blocks) {
        const b = block.trim();
        if (!b) continue;
        if (!/<[\w\/]/.test(b)) {
            out.push('<p>' + b.replace(/\n/g, '<br>') + '</p>');
        } else {
            out.push(b);
        }
    }
    ta.value = out.join('\n\n').replace(/<p>\s*<\/p>/g, '');
    updatePreview();
}

// ===== AI 辅助 =====
function aiHelper(action) {
    const content = contentArea.value;
    if (!content.trim()) { showAiError('请先填写内容'); return; }
    const title = document.getElementById('articleTitle').value;
    const labels = { summarize: '📝 摘要', polish: '✨ 润色', suggest_title: '🏆 标题' };
    aiLastAction = action;
    document.getElementById('aiLoading').style.display = 'flex';
    document.getElementById('aiResult').style.display = 'none';
    document.getElementById('aiError').style.display = 'none';
    document.getElementById('aiResultLabel').textContent = labels[action] || action;
    document.querySelectorAll('.ai-btn').forEach(b => b.disabled = true);

    fetch('/myweb/admin/ai_helper.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=' + action + '&content=' + encodeURIComponent(content) + '&title=' + encodeURIComponent(title)
    })
    .then(r => r.json())
    .then(res => {
        document.getElementById('aiLoading').style.display = 'none';
        document.querySelectorAll('.ai-btn').forEach(b => b.disabled = false);
        if (res.success) {
            aiLastResult = res.data;
            document.getElementById('aiResultContent').textContent = res.data;
            document.getElementById('aiResult').style.display = 'block';
        } else { showAiError(res.error || '失败'); }
    })
    .catch(err => {
        document.getElementById('aiLoading').style.display = 'none';
        document.querySelectorAll('.ai-btn').forEach(b => b.disabled = false);
        showAiError('网络错误');
    });
}

function showAiError(msg) {
    const el = document.getElementById('aiError');
    el.textContent = '⚠️ ' + msg;
    el.style.display = 'block';
}

function aiInsertResult() {
    if (aiLastAction === 'summarize') {
        document.getElementById('articleSummary').value = aiLastResult;
    } else if (aiLastAction === 'generate') {
        if (aiLastResult?.title) {
            document.getElementById('articleTitle').value = aiLastResult.title;
            if (aiLastResult.summary) document.getElementById('articleSummary').value = aiLastResult.summary;
            if (aiLastResult.content) { contentArea.value = aiLastResult.content; updatePreview(); }
        }
    } else if (aiLastAction === 'suggest_title') {
        const titles = aiLastResult.split('\n').filter(t => t.trim());
        let msg = '选择标题:\n' + titles.map((t,i) => (i+1)+'. '+t.replace(/^\d+[.、\s)]*\s*/, '')).join('\n');
        const choice = prompt(msg, '1');
        if (choice) {
            const idx = parseInt(choice) - 1;
            if (idx >= 0 && idx < titles.length) {
                document.getElementById('articleTitle').value = titles[idx].replace(/^\d+[.、\s)]*\s*/, '');
            }
        }
    } else {
        const pos = contentArea.selectionStart;
        contentArea.value = contentArea.value.slice(0, pos) + '\n' + aiLastResult + '\n' + contentArea.value.slice(pos);
        updatePreview();
    }
    document.getElementById('aiResult').style.display = 'none';
}

function showExpandDialog() {
    if (!contentArea.value.trim()) { showAiError('请先填写内容'); return; }
    const choice = prompt('续写长度:\n1.短 2.中 3.长', '2');
    if (!choice) return;
    const map = {'1':'short','2':'medium','3':'long'};
    expandContent(map[choice] || 'medium');
}

function expandContent(length) {
    const content = contentArea.value;
    const title = document.getElementById('articleTitle').value;
    aiLastAction = 'expand';
    document.getElementById('aiLoading').style.display = 'flex';
    document.getElementById('aiResult').style.display = 'none';
    document.getElementById('aiResultLabel').textContent = '✍️ 续写';
    document.querySelectorAll('.ai-btn').forEach(b => b.disabled = true);

    fetch('/myweb/admin/ai_helper.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=expand&content=' + encodeURIComponent(content) + '&title=' + encodeURIComponent(title) + '&length=' + length
    })
    .then(r => r.json())
    .then(res => {
        document.getElementById('aiLoading').style.display = 'none';
        document.querySelectorAll('.ai-btn').forEach(b => b.disabled = false);
        if (res.success) {
            aiLastResult = res.data;
            document.getElementById('aiResultContent').textContent = res.data;
            document.getElementById('aiResult').style.display = 'block';
        } else { showAiError(res.error || '失败'); }
    })
    .catch(err => {
        document.getElementById('aiLoading').style.display = 'none';
        document.querySelectorAll('.ai-btn').forEach(b => b.disabled = false);
        showAiError('网络错误');
    });
}

function showGenerateDialog() {
    const topic = prompt('文章主题:', '');
    if (!topic) return;
    const style = prompt('风格: 1.通用 2.正式 3.口语 4.教程', '1');
    if (!style) return;
    const map = {'1':'general','2':'formal','3':'casual','4':'tech'};

    aiLastAction = 'generate';
    document.getElementById('aiLoading').style.display = 'flex';
    document.getElementById('aiResult').style.display = 'none';
    document.getElementById('aiResultLabel').textContent = '🌱 生成';
    document.querySelectorAll('.ai-btn').forEach(b => b.disabled = true);

    fetch('/myweb/admin/ai_helper.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=generate&content=' + encodeURIComponent(topic) + '&style=' + (map[style]||'general')
    })
    .then(r => r.json())
    .then(res => {
        document.getElementById('aiLoading').style.display = 'none';
        document.querySelectorAll('.ai-btn').forEach(b => b.disabled = false);
        if (res.success) {
            aiLastResult = res.data;
            document.getElementById('aiResultContent').textContent = typeof res.data === 'object' ? ('标题:'+res.data.title+'\n\n'+res.data.content) : res.data;
            document.getElementById('aiResult').style.display = 'block';
        } else { showAiError(res.error || '失败'); }
    })
    .catch(err => {
        document.getElementById('aiLoading').style.display = 'none';
        document.querySelectorAll('.ai-btn').forEach(b => b.disabled = false);
        showAiError('网络错误');
    });
}

// ===== 标签 =====
function parseTags(str) { return str.split(/[,\s]+/).map(t => t.trim()).filter(t => t); }
function renderTagChips() {
    const input = document.getElementById('tagInput');
    const container = document.getElementById('tagChips');
    const hidden = document.getElementById('tagHiddenContainer');
    const tags = [...new Set(parseTags(input.value))];
    container.innerHTML = tags.map(t => '<span class="tag tag-selected" onclick="removeTag(\'' + t.replace(/'/g,"\\'") + '\')">' + t + ' ✕</span>').join('');
    hidden.innerHTML = tags.map(t => '<input type="hidden" name="tags[]" value="' + t.replace(/"/g,'&quot;') + '">').join('');
}
function addTag(name) { const i = document.getElementById('tagInput'); const t = parseTags(i.value); if (!t.includes(name)) { t.push(name); i.value = t.join(', '); renderTagChips(); } }
function removeTag(name) { const i = document.getElementById('tagInput'); i.value = parseTags(i.value).filter(t => t !== name).join(', '); renderTagChips(); }
document.addEventListener('DOMContentLoaded', () => {
    renderTagChips();
    document.getElementById('tagInput').addEventListener('input', renderTagChips);
});

// ===== 快捷键 =====
contentArea.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('editorStatus').textContent = '已保存';
        setTimeout(() => document.getElementById('editorStatus').textContent = '', 2000);
    }
});
</script>
<?php require_once '../includes/footer.php'; ?>
