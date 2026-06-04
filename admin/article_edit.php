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
    // 加载已有标签
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
    $tagNames = $_POST['tags'] ?? []; // array of tag names

    // editor 不能直接发布，强制提交为待审核
    if (!hasRole('super_admin', 'admin') && $status === 'published') {
        $status = 'pending';
    }

    if (!$title) $error = '请输入文章标题';

    // 处理封面图片上传
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
                    if ($tagRow) {
                        $atStmt->execute([$id, $tagRow['id']]);
                    }
                }
            }
        }

        redirect('/myweb/admin/articles.php');
    }
}
?>

<style>
.ai-panel { width: 280px; flex-shrink: 0; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); position: sticky; top: 84px; align-self: flex-start; max-height: calc(100vh - 100px); overflow-y: auto; }
</style>

<div class="admin-layout">
    <aside class="admin-sidebar"><?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?></aside>
    <main class="admin-main">
        <div class="editor-layout">
            <div class="editor-form">
                <h2><?= $id ? '编辑文章' : '写文章' ?></h2>
                <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
                <form method="post" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <div class="form-group">
                        <label>标题</label>
                        <input type="text" name="title" id="articleTitle" required value="<?= htmlspecialchars($article['title']) ?>">
                    </div>
                    <div class="form-group">
                        <label>摘要</label>
                        <textarea name="summary" id="articleSummary" rows="2"><?= htmlspecialchars($article['summary']) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>内容 (支持HTML)</label>
                        <div class="editor-tabs">
                            <button type="button" class="editor-tab active" data-tab="edit" onclick="switchEditorTab('edit')">✏️ 编辑</button>
                            <button type="button" class="editor-tab" data-tab="preview" onclick="switchEditorTab('preview')">👁️ 预览</button>
                        </div>
                        <div class="editor-container">
                            <textarea name="content" id="content" rows="15"><?= htmlspecialchars($article['content']) ?></textarea>
                            <div id="previewArea" class="article-content-wrap" style="display:none;background:var(--bg-body);padding:24px 28px;min-height:300px">
                                <div class="article-content" id="previewContent"></div>
                            </div>
                        </div>
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
                            <small style="color:var(--text-muted);display:block;margin-top:4px">提交后需要管理员审核才能发布</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>标签</label>
                        <input type="text" id="tagInput" placeholder="输入标签，空格或逗号分隔" value="<?= htmlspecialchars(implode(', ', array_column($articleTags, 'name'))) ?>" style="margin-bottom:6px">
                        <div id="tagHiddenContainer"></div>
                        <div id="tagChips" class="tag-chips">
                            <?php foreach ($articleTags as $t): ?>
                            <span class="tag tag-selected" data-tag="<?= htmlspecialchars($t['name']) ?>"><?= htmlspecialchars($t['name']) ?> ✕</span>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!empty($allTags)): ?>
                        <div class="tag-suggestions">
                            <small style="color:var(--text-muted)">常用标签: </small>
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
                            <br><img src="/myweb/<?= $article['cover_image'] ?>" style="max-width:200px;margin-top:8px">
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-primary">保存</button>
                    <a href="/myweb/admin/articles.php" class="btn">取消</a>
                </form>
            </div>

            <aside class="ai-panel">
                <div class="ai-panel-header">
                    <h3>✨ AI 助手</h3>
                </div>
                <div class="ai-panel-body">
                    <p class="ai-panel-desc">基于文章内容智能生成</p>
                    <div class="ai-actions">
                        <button class="ai-btn" onclick="aiHelper('summarize')">
                            <span class="ai-btn-icon">📝</span> 生成摘要
                        </button>
                        <button class="ai-btn" onclick="aiHelper('polish')">
                            <span class="ai-btn-icon">✨</span> 润色全文
                        </button>
                        <button class="ai-btn" onclick="showExpandDialog()">
                            <span class="ai-btn-icon">✍️</span> 续写内容
                        </button>
                        <div style="border-top:1px solid var(--border);margin:8px 0"></div>
                        <button class="ai-btn" onclick="showGenerateDialog()" style="border-color:var(--accent)">
                            <span class="ai-btn-icon">🌱</span> 全文生成
                        </button>
                        <button class="ai-btn" onclick="aiHelper('suggest_title')" style="border-color:var(--purple)">
                            <span class="ai-btn-icon">🏆</span> 标题优化
                        </button>
                        <button class="ai-btn" onclick="localFormat()">
                            <span class="ai-btn-icon">📐</span> 自动排版
                        </button>
                    </div>

                    <div id="aiLoading" class="ai-loading" style="display:none">
                        <div class="ai-spinner"></div>
                        <span>AI 思考中…</span>
                    </div>
                    <div id="aiError" class="ai-error"></div>

                    <div id="aiResult" class="ai-result" style="display:none">
                        <div class="ai-result-header">
                            <span id="aiResultLabel"></span>
                            <button class="ai-insert-btn" onclick="aiInsertResult()">插入结果</button>
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

// 本地排版 — 不动原文文字，仅清理 HTML 结构
function localFormat() {
    const ta = document.getElementById('content');
    let html = ta.value;
    if (!html.trim()) return;

    document.getElementById('aiLoading').style.display = 'flex';
    document.getElementById('aiResult').style.display = 'none';
    document.getElementById('aiError').style.display = 'none';

    // 使用 setTimeout 让 loading 先渲染出来
    setTimeout(() => {
        const original = html;

        // 1. 去掉超过 2 行的连续空行
        html = html.replace(/\n{3,}/g, '\n\n');

        // 2. 去掉行首尾的空白
        html = html.replace(/^[ \t]+/gm, '');
        html = html.replace(/[ \t]+$/gm, '');

        // 3. 解析成块，将裸文本（无 HTML 标签的段落）用 <p> 包裹
        const blocks = html.split(/\n\n+/);
        const out = [];
        for (const block of blocks) {
            const b = block.trim();
            if (!b) continue;
            // 如果块内没有 HTML 标签，视为纯文本段落
            if (!/<[\w\/]/.test(b)) {
                // 块内换行转 <br>
                out.push('<p>' + b.replace(/\n/g, '<br>') + '</p>');
            } else {
                out.push(b);
            }
        }
        html = out.join('\n\n');

        // 4. 清理空 <p> 标签
        html = html.replace(/<p>\s*<\/p>/g, '');

        // 5. 确保 <p> 之间没有多余空行
        html = html.replace(/(<\/p>)\s*\n\s*(<p>)/g, '$1\n$2');

        // 如果结果没变，提示
        if (html === original) {
            document.getElementById('aiLoading').style.display = 'none';
            showAiError('内容已是最佳格式，无需调整');
            return;
        }

        ta.value = html;
        document.getElementById('aiLoading').style.display = 'none';
        document.getElementById('aiResultLabel').textContent = '📐 自动排版';
        document.getElementById('aiResultContent').textContent = '排版完成，内容无丢失 ✓';
        aiLastResult = html;
        aiLastAction = 'format';
        document.getElementById('aiResult').style.display = 'block';
    }, 100);
}

function aiHelper(action) {
    const content = document.getElementById('content').value;
    if (!content.trim()) {
        showAiError('请先填写文章内容');
        return;
    }

    const title = document.getElementById('articleTitle').value;
    const labels = { summarize: '📝 生成摘要', polish: '✨ 润色全文', expand: '✍️ 续写内容', generate: '🌱 全文生成', suggest_title: '🏆 标题优化' };
    aiLastAction = action;

    document.getElementById('aiLoading').style.display = 'flex';
    document.getElementById('aiResult').style.display = 'none';
    document.getElementById('aiError').style.display = 'none';
    document.getElementById('aiResultLabel').textContent = labels[action] || action;

    // 禁用所有按钮
    document.querySelectorAll('.ai-btn').forEach(b => b.disabled = true);

    fetch('/myweb/admin/ai_helper.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=' + encodeURIComponent(action) + '&content=' + encodeURIComponent(content) + '&title=' + encodeURIComponent(title)
    })
    .then(r => r.json())
    .then(res => {
        document.getElementById('aiLoading').style.display = 'none';
        document.querySelectorAll('.ai-btn').forEach(b => b.disabled = false);
        if (res.success) {
            aiLastResult = res.data;
            document.getElementById('aiResultContent').textContent = res.data;
            document.getElementById('aiResult').style.display = 'block';
        } else {
            showAiError(res.error || '操作失败，请重试');
        }
    })
    .catch(err => {
        document.getElementById('aiLoading').style.display = 'none';
        document.querySelectorAll('.ai-btn').forEach(b => b.disabled = false);
        showAiError('网络错误: ' + err.message);
    });
}

function showAiError(msg) {
    const el = document.getElementById('aiError');
    el.textContent = '⚠️ ' + msg;
    el.style.display = 'block';
}

function aiInsertResult() {
    if (aiLastAction === 'format') {
        document.getElementById('content').value = aiLastResult;
    } else if (aiLastAction === 'summarize') {
        document.querySelector('textarea[name="summary"]').value = aiLastResult;
    } else if (aiLastAction === 'generate') {
        if (aiLastResult && aiLastResult.title) {
            document.querySelector('input[name="title"]').value = aiLastResult.title;
            if (aiLastResult.summary) document.querySelector('textarea[name="summary"]').value = aiLastResult.summary;
            if (aiLastResult.content) document.getElementById('content').value = aiLastResult.content;
            alert('文章已生成完毕 ✓');
        }
    } else if (aiLastAction === 'suggest_title') {
        // 显示候选标题弹窗
        const titles = aiLastResult.split('\n').filter(t => t.trim());
        let msg = 'AI 推荐标题：\n';
        titles.forEach((t, i) => { msg += '\n' + (i+1) + '. ' + t.replace(/^\d+[.、\s)]*\s*/, ''); });
        msg += '\n\n请输入编号选择要使用的标题（1-' + titles.length + '），或取消';
        const choice = prompt(msg, '1');
        if (choice) {
            const idx = parseInt(choice) - 1;
            if (idx >= 0 && idx < titles.length) {
                const selected = titles[idx].replace(/^\d+[.、\s)]*\s*/, '');
                document.querySelector('input[name="title"]').value = selected;
            } else {
                alert('无效选择');
            }
        }
    } else {
        const ta = document.getElementById('content');
        const pos = ta.selectionStart;
        ta.value = ta.value.slice(0, pos) + '\n' + aiLastResult + '\n' + ta.value.slice(pos);
    }
    document.getElementById('aiResult').style.display = 'none';
}

// 续写内容弹窗 — 选择长度
function showExpandDialog() {
    const content = document.getElementById('content').value;
    if (!content.trim()) { showAiError('请先填写文章内容'); return; }
    const choice = prompt('选择续写长度：\n1. 短 (50-100字)\n2. 中 (100-200字)\n3. 长 (200-400字)', '2');
    if (!choice) return;
    const map = { '1': 'short', '2': 'medium', '3': 'long' };
    const length = map[choice] || 'medium';
    aiHelperExpand(length);
}

function aiHelperExpand(length) {
    const content = document.getElementById('content').value;
    const title = document.getElementById('articleTitle').value;
    aiLastAction = 'expand';
    document.getElementById('aiLoading').style.display = 'flex';
    document.getElementById('aiResult').style.display = 'none';
    document.getElementById('aiError').style.display = 'none';
    document.getElementById('aiResultLabel').textContent = '✍️ 续写内容';
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
        } else {
            showAiError(res.error || '操作失败');
        }
    })
    .catch(err => {
        document.getElementById('aiLoading').style.display = 'none';
        document.querySelectorAll('.ai-btn').forEach(b => b.disabled = false);
        showAiError('网络错误: ' + err.message);
    });
}

// 全文生成弹窗
function showGenerateDialog() {
    const topic = prompt('请输入文章主题（一句话描述）：\n\n例如：树莓派搭建个人云存储', '');
    if (!topic) return;
    const style = prompt('选择文章风格：\n1. 通用 (默认)\n2. 正式专业\n3. 轻松口语\n4. 技术教程', '1');
    if (!style) return;
    const styleMap = { '1': 'general', '2': 'formal', '3': 'casual', '4': 'tech' };
    doGenerate(topic, styleMap[style] || 'general');
}

function doGenerate(topic, style) {
    aiLastAction = 'generate';
    document.getElementById('aiLoading').style.display = 'flex';
    document.getElementById('aiResult').style.display = 'none';
    document.getElementById('aiError').style.display = 'none';
    document.getElementById('aiResultLabel').textContent = '🌱 全文生成';
    document.querySelectorAll('.ai-btn').forEach(b => b.disabled = true);

    fetch('/myweb/admin/ai_helper.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=generate&content=' + encodeURIComponent(topic) + '&style=' + style
    })
    .then(r => r.json())
    .then(res => {
        document.getElementById('aiLoading').style.display = 'none';
        document.querySelectorAll('.ai-btn').forEach(b => b.disabled = false);
        if (res.success) {
            aiLastResult = res.data;
            // 如果是对象则显示摘要信息
            if (typeof res.data === 'object' && res.data.title) {
                document.getElementById('aiResultContent').textContent = '标题：' + res.data.title + '\n\n摘要：' + (res.data.summary || '无') + '\n\n正文长度：' + (res.data.content || '').length + ' 字';
            } else if (typeof res.data === 'string') {
                document.getElementById('aiResultContent').textContent = res.data;
            } else {
                document.getElementById('aiResultContent').textContent = JSON.stringify(res.data);
            }
            document.getElementById('aiResult').style.display = 'block';
        } else {
            showAiError(res.error || '操作失败');
        }
    })
    .catch(err => {
        document.getElementById('aiLoading').style.display = 'none';
        document.querySelectorAll('.ai-btn').forEach(b => b.disabled = false);
        showAiError('网络错误: ' + err.message);
    });
}

// 编辑/预览切换
function switchEditorTab(tab) {
    document.querySelectorAll('.editor-tab').forEach(t => t.classList.remove('active'));
    document.querySelector('.editor-tab[data-tab="' + tab + '"]').classList.add('active');
    const textarea = document.getElementById('content');
    const preview = document.getElementById('previewArea');
    if (tab === 'edit') {
        textarea.style.display = 'block';
        preview.style.display = 'none';
    } else {
        textarea.style.display = 'none';
        preview.style.display = 'block';
        document.getElementById('previewContent').innerHTML = textarea.value;
    }
}

// ===== 标签管理 =====
function parseTags(str) {
    return str.split(/[,\s]+/).map(t => t.trim()).filter(t => t.length > 0);
}

function renderTagChips() {
    const input = document.getElementById('tagInput');
    const container = document.getElementById('tagChips');
    const hiddenContainer = document.getElementById('tagHiddenContainer');
    const tags = parseTags(input.value);
    // 去重
    const unique = [];
    const seen = {};
    tags.forEach(t => { if (!seen[t]) { seen[t] = true; unique.push(t); } });
    container.innerHTML = unique.map(t => '<span class="tag tag-selected" data-tag="' + t.replace(/"/g, '&quot;') + '" onclick="removeTag(\'' + t.replace(/'/g, "\\'") + '\')">' + t + ' ✕</span>').join('');
    // 写入 hidden inputs
    hiddenContainer.innerHTML = unique.map(t => '<input type="hidden" name="tags[]" value="' + t.replace(/"/g, '&quot;') + '">').join('');
}

function addTag(name) {
    const input = document.getElementById('tagInput');
    const existing = parseTags(input.value);
    if (existing.indexOf(name) === -1) {
        existing.push(name);
        input.value = existing.join(', ');
        renderTagChips();
    }
}

function removeTag(name) {
    const input = document.getElementById('tagInput');
    const tags = parseTags(input.value).filter(t => t !== name);
    input.value = tags.join(', ');
    renderTagChips();
}

document.addEventListener('DOMContentLoaded', function() {
    // 同步 tag hidden inputs
    renderTagChips();
    // 输入时实时同步
    document.getElementById('tagInput').addEventListener('input', renderTagChips);
    // 点击 tag-suggest 防重复标注（已由全局 addTag 处理）
});
</script>
<script src="/myweb/js/editor.js" defer></script>
<?php require_once '../includes/footer.php'; ?>
