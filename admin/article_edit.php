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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $summary = trim($_POST['summary'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $status = $_POST['status'] ?? 'draft';
    $tagNames = $_POST['tags'] ?? [];

    if (!hasRole('super_admin', 'admin') && $status === 'published') $status = 'pending';
    if (!$title) $error = '请输入文章标题';

    $cover_image = $article['cover_image'];
    if (!$error && !empty($_FILES['cover_image']['name'])) {
        if ($_FILES['cover_image']['size'] > 5 * 1024 * 1024) {
            $error = '封面图片不能超过 5MB';
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['cover_image']['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, ['image/jpeg','image/png','image/gif','image/webp']) || !getimagesize($_FILES['cover_image']['tmp_name'])) {
                $error = '仅支持 JPG/PNG/GIF/WebP 格式';
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
        if ($id) {
            db()->prepare("DELETE FROM article_tags WHERE article_id = ?")->execute([$id]);
            if ($tagNames) {
                $tagNames = array_unique(array_map('trim', $tagNames));
                $tagStmt = db()->prepare("INSERT IGNORE INTO tags (name) VALUES (?)");
                $atStmt = db()->prepare("INSERT INTO article_tags (article_id, tag_id) SELECT ?, id FROM tags WHERE name = ?");
                foreach ($tagNames as $n) {
                    if ($n === '') continue;
                    $tagStmt->execute([$n]);
                    $atStmt->execute([$id, $n]);
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
        <div class="editor-wrap">
            <!-- 编辑区 -->
            <div class="editor-main">
                <form method="post" enctype="multipart/form-data" id="articleForm">
                    <?= csrfField() ?>
                    <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

                    <input type="text" name="title" id="articleTitle" class="editor-title" required
                           value="<?= htmlspecialchars($article['title']) ?>"
                           placeholder="文章标题...">

                    <div class="editor-bar">
                        <div class="editor-bar-left">
                            <button type="button" class="ebtn" onclick="wrapSel('<h2>','</h2>')" title="二级标题">H2</button>
                            <button type="button" class="ebtn" onclick="wrapSel('<h3>','</h3>')" title="三级标题">H3</button>
                            <span class="ebtn-sep"></span>
                            <button type="button" class="ebtn" onclick="wrapSel('<strong>','</strong>')" title="粗体"><b>B</b></button>
                            <button type="button" class="ebtn" onclick="wrapSel('<code>','</code>')" title="行内代码">&lt;/&gt;</button>
                            <span class="ebtn-sep"></span>
                            <button type="button" class="ebtn" onclick="wrapBlock('pre')" title="代码块">{ }</button>
                            <button type="button" class="ebtn" onclick="wrapBlock('q')" title="引用">❝</button>
                            <button type="button" class="ebtn" onclick="wrapSel('','','link')" title="插入链接">🔗</button>
                        </div>
                        <div class="editor-bar-right">
                            <button type="button" class="ebtn ebtn-ai" onclick="aiFormat()" title="AI 自动排版">✨ 排版</button>
                            <button type="button" class="ebtn" onclick="togglePreview()" id="previewToggle">👁 预览</button>
                        </div>
                    </div>

                    <div class="editor-area" id="editorArea">
                        <textarea name="content" id="content" class="editor-textarea"
                                  placeholder="开始写作...&#10;&#10;支持 HTML 标签：<h2>标题</h2> <p>段落</p> <pre><code>代码</code></pre>&#10;粘贴纯文本可点击「✨ 排版」自动格式化"><?= htmlspecialchars($article['content']) ?></textarea>
                        <div class="editor-preview" id="editorPreview" style="display:none"></div>
                    </div>

                    <div class="editor-actions">
                        <button type="submit" class="btn btn-primary"><?= $id ? '保存修改' : '发布文章' ?></button>
                        <a href="/myweb/admin/articles.php" class="btn">取消</a>
                    </div>
                </form>
            </div>

            <!-- 设置面板 -->
            <aside class="editor-sidebar">
                <div class="es-card">
                    <div class="es-card-title">发布设置</div>
                    <div class="form-group">
                        <label>状态</label>
                        <select name="status" form="articleForm">
                            <option value="draft" <?= $article['status']=='draft'?'selected':'' ?>>草稿</option>
                            <option value="pending" <?= $article['status']=='pending'?'selected':'' ?>>待审核</option>
                            <option value="published" <?= $article['status']=='published'?'selected':'' ?>>发布</option>
                            <option value="archived" <?= $article['status']=='archived'?'selected':'' ?>>归档</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>分类</label>
                        <select name="category_id" form="articleForm">
                            <option value="">无分类</option>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $c['id']==$article['category_id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="es-card">
                    <div class="es-card-title">标签</div>
                    <input type="text" id="tagInput" form="none" placeholder="输入标签，空格或逗号分隔"
                           value="<?= htmlspecialchars(implode(', ', array_column($articleTags, 'name'))) ?>">
                    <div id="tagHiddenContainer"></div>
                    <div id="tagChips" class="tag-chips" style="margin-top:6px"></div>
                    <?php if ($allTags): ?>
                    <div class="tag-suggestions" style="margin-top:8px">
                        <?php foreach ($allTags as $t): ?>
                        <span class="tag tag-suggest" onclick="addTag('<?= htmlspecialchars($t['name'], ENT_QUOTES) ?>')"><?= htmlspecialchars($t['name']) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="es-card">
                    <div class="es-card-title">封面图片</div>
                    <input type="file" name="cover_image" form="articleForm" accept="image/*" style="font-size:0.85rem;width:100%">
                    <?php if ($article['cover_image']): ?>
                    <img src="/myweb/<?= $article['cover_image'] ?>" style="max-width:100%;margin-top:8px;border-radius:8px">
                    <?php endif; ?>
                </div>

                <div class="es-card">
                    <div class="es-card-title">摘要</div>
                    <textarea name="summary" form="articleForm" rows="2" placeholder="可选，留空自动取正文前200字"
                              style="width:100%;padding:8px 10px;background:var(--gray-900);border:1px solid var(--gray-700);border-radius:6px;color:var(--gray-200);font-size:0.85rem;resize:vertical"><?= htmlspecialchars($article['summary']) ?></textarea>
                </div>

                <div class="es-card">
                    <div class="es-card-title">🤖 AI 助手</div>
                    <div class="ai-btns">
                        <button class="ai-b" onclick="aiAct('summarize')">📝 生成摘要</button>
                        <button class="ai-b" onclick="aiAct('polish')">✨ 润色全文</button>
                        <button class="ai-b" onclick="aiAct('suggest_title')">🏆 标题优化</button>
                        <button class="ai-b" onclick="aiExpand()">✍️ 续写</button>
                        <button class="ai-b ai-b-accent" onclick="showGen()">🌱 全文生成</button>
                    </div>
                    <div id="aiLoading" style="display:none;padding:8px;color:var(--gray-500);font-size:0.85rem">AI 思考中…</div>
                    <div id="aiError" style="display:none;padding:8px;color:var(--danger);font-size:0.82rem"></div>
                    <div id="aiResult" style="display:none;margin-top:8px;padding:8px;background:var(--gray-900);border:1px solid var(--gray-700);border-radius:6px;font-size:0.82rem;max-height:200px;overflow-y:auto">
                        <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                            <span id="aiResultLabel" style="color:var(--accent-light);font-weight:600"></span>
                            <button onclick="aiInsert()" style="padding:2px 8px;font-size:0.75rem;border:1px solid var(--accent);background:transparent;color:var(--accent-light);border-radius:4px;cursor:pointer">插入</button>
                        </div>
                        <div id="aiResultContent" style="white-space:pre-wrap;color:var(--gray-300)"></div>
                    </div>
                </div>
            </aside>
        </div>
    </main>
</div>

<script>
const ta = document.getElementById('content');
const preview = document.getElementById('editorPreview');
let aiResult = '', aiAction = '';

// ===== 预览切换 =====
function togglePreview() {
    const btn = document.getElementById('previewToggle');
    if (preview.style.display === 'none') {
        preview.innerHTML = ta.value || '<p style="color:var(--gray-500)">暂无内容</p>';
        preview.style.display = 'block';
        ta.style.display = 'none';
        btn.textContent = '✏ 编辑';
    } else {
        preview.style.display = 'none';
        ta.style.display = 'block';
        btn.textContent = '👁 预览';
    }
}

// ===== 格式工具 =====
function wrapSel(open, close, type) {
    const v = ta.value, s = ta.selectionStart, e = ta.selectionEnd;
    let sel = v.substring(s, e);
    if (type === 'link') {
        const url = prompt('链接地址:', 'https://');
        if (!url) return;
        sel = sel || url;
        const h = '<a href="'+url+'" target="_blank">'+sel+'</a>';
        ta.value = v.substring(0,s) + h + v.substring(e);
        ta.focus(); ta.setSelectionRange(s+h.length,s+h.length);
        return;
    }
    sel = sel || (open.includes('h') ? '标题' : (open.includes('strong') ? '粗体' : (open.includes('code') ? '代码' : '')));
    const h = open + sel + close;
    ta.value = v.substring(0,s) + h + v.substring(e);
    ta.focus(); ta.setSelectionRange(s+open.length, s+open.length+sel.length);
}

function wrapBlock(type) {
    const v = ta.value, s = ta.selectionStart, e = ta.selectionEnd;
    let sel = v.substring(s, e) || '内容';
    let h;
    if (type === 'pre') h = '\n<pre><code>'+sel+'</code></pre>\n';
    else if (type === 'q') h = '\n<blockquote><p>'+sel+'</p></blockquote>\n';
    ta.value = v.substring(0,s) + h + v.substring(e);
    ta.focus();
}

// ===== AI 排版 =====
function aiFormat() {
    if (!ta.value.trim()) return;
    const btn = document.querySelector('.ebtn-ai');
    btn.textContent = '⏳'; btn.disabled = true;

    fetch('/myweb/ai_format.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'content='+encodeURIComponent(ta.value)+'&title='+encodeURIComponent(document.getElementById('articleTitle').value)+'&csrf_token='+document.querySelector('[name=csrf_token]').value
    })
    .then(r => r.json())
    .then(res => {
        if (res.success && res.html) { ta.value = res.html; }
        btn.textContent = '✓';
    })
    .catch(() => btn.textContent = '✗')
    .finally(() => setTimeout(() => { btn.textContent = '✨ 排版'; btn.disabled = false; }, 2000));
}

// ===== AI 辅助 =====
function aiAct(action) {
    if (!ta.value.trim()) { document.getElementById('aiError').textContent='⚠️ 请先填写内容'; document.getElementById('aiError').style.display='block'; return; }
    aiAction = action;
    const labels = {summarize:'📝 摘要', polish:'✨ 润色', suggest_title:'🏆 标题'};
    document.getElementById('aiLoading').style.display='block';
    document.getElementById('aiResult').style.display='none';
    document.getElementById('aiError').style.display='none';
    document.getElementById('aiResultLabel').textContent=labels[action]||action;

    fetch('/myweb/admin/ai_helper.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'action='+action+'&content='+encodeURIComponent(ta.value)+'&title='+encodeURIComponent(document.getElementById('articleTitle').value)
    })
    .then(r=>r.json())
    .then(res => {
        document.getElementById('aiLoading').style.display='none';
        if (res.success) { aiResult=res.data; document.getElementById('aiResultContent').textContent=res.data; document.getElementById('aiResult').style.display='block'; }
        else { document.getElementById('aiError').textContent='⚠️ '+(res.error||'失败'); document.getElementById('aiError').style.display='block'; }
    })
    .catch(() => { document.getElementById('aiLoading').style.display='none'; document.getElementById('aiError').textContent='⚠️ 网络错误'; document.getElementById('aiError').style.display='block'; });
}

function aiInsert() {
    if (aiAction === 'summarize') document.querySelector('[name=summary]').value = aiResult;
    else if (aiAction === 'suggest_title') {
        const ts = aiResult.split('\n').filter(t=>t.trim());
        const c = prompt('选择标题:\n'+ts.map((t,i)=>(i+1)+'. '+t.replace(/^\d+[.、\s)]*\s*/,'')).join('\n'), '1');
        if (c) { const i=parseInt(c)-1; if (i>=0&&i<ts.length) document.getElementById('articleTitle').value=ts[i].replace(/^\d+[.、\s)]*\s*/,''); }
    } else { const p=ta.selectionStart; ta.value=ta.value.slice(0,p)+'\n'+aiResult+'\n'+ta.value.slice(p); }
    document.getElementById('aiResult').style.display='none';
}

function aiExpand() {
    if (!ta.value.trim()) return;
    const c = prompt('续写长度: 1.短 2.中 3.长', '2'); if (!c) return;
    const m = {'1':'short','2':'medium','3':'long'};
    aiAction = 'expand';
    document.getElementById('aiLoading').style.display='block';
    document.getElementById('aiResult').style.display='none';
    fetch('/myweb/admin/ai_helper.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=expand&content='+encodeURIComponent(ta.value)+'&length='+(m[c]||'medium')})
    .then(r=>r.json()).then(res=>{document.getElementById('aiLoading').style.display='none';if(res.success){aiResult=res.data;document.getElementById('aiResultContent').textContent=res.data;document.getElementById('aiResult').style.display='block';document.getElementById('aiResultLabel').textContent='✍️ 续写';}})
    .catch(()=>{document.getElementById('aiLoading').style.display='none';});
}

function showGen() {
    const t=prompt('文章主题:',''); if(!t) return;
    const s=prompt('风格: 1.通用 2.正式 3.口语 4.教程','1'); if(!s) return;
    const m={'1':'general','2':'formal','3':'casual','4':'tech'};
    aiAction='generate';
    document.getElementById('aiLoading').style.display='block';
    document.getElementById('aiResult').style.display='none';
    document.getElementById('aiResultLabel').textContent='🌱 生成';
    fetch('/myweb/admin/ai_helper.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=generate&content='+encodeURIComponent(t)+'&style='+(m[s]||'general')})
    .then(r=>r.json()).then(res=>{document.getElementById('aiLoading').style.display='none';if(res.success){aiResult=res.data;document.getElementById('aiResultContent').textContent=typeof res.data==='object'?('标题:'+res.data.title+'\n\n'+res.data.content):res.data;document.getElementById('aiResult').style.display='block';}})
    .catch(()=>{document.getElementById('aiLoading').style.display='none';});
}

// ===== 标签 =====
function parseTags(s) { return [...new Set(s.split(/[,\s]+/).map(t=>t.trim()).filter(t=>t))]; }
function renderTags() {
    const v = document.getElementById('tagInput').value;
    const tags = parseTags(v);
    document.getElementById('tagChips').innerHTML = tags.map(t => '<span class="tag tag-selected" onclick="rmTag(\''+t.replace(/'/g,"\\'")+'\')">'+t+' ✕</span>').join('');
    document.getElementById('tagHiddenContainer').innerHTML = tags.map(t => '<input type="hidden" name="tags[]" value="'+t.replace(/"/g,'&quot;')+'" form="articleForm">').join('');
}
function addTag(n) { const i=document.getElementById('tagInput'); const t=parseTags(i.value); if(!t.includes(n)){t.push(n);i.value=t.join(', ');renderTags();} }
function rmTag(n) { const i=document.getElementById('tagInput'); i.value=parseTags(i.value).filter(t=>t!==n).join(', ');renderTags(); }
document.addEventListener('DOMContentLoaded',()=>{renderTags();document.getElementById('tagInput').addEventListener('input',renderTags);});

// ===== 快捷键 =====
ta.addEventListener('keydown',function(e){if((e.ctrlKey||e.metaKey)&&e.key==='s'){e.preventDefault();document.getElementById('editorActions').style.opacity='0.5';setTimeout(()=>document.getElementById('editorActions').style.opacity='1',500);}});
document.addEventListener('keydown',function(e){if(e.ctrlKey&&e.key==='p'){e.preventDefault();togglePreview();}});
</script>
<?php require_once '../includes/footer.php'; ?>
