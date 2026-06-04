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
                $error = '仅支持 JPG/PNG/GIF/WebP';
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
                $ts = db()->prepare("INSERT IGNORE INTO tags (name) VALUES (?)");
                $at = db()->prepare("INSERT INTO article_tags (article_id, tag_id) SELECT ?, id FROM tags WHERE name = ?");
                foreach ($tagNames as $n) {
                    if ($n === '') continue;
                    $ts->execute([$n]);
                    $at->execute([$id, $n]);
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
        <div class="e-root">
            <!-- 顶部标题区 -->
            <div class="e-top">
                <input type="text" name="title" id="etitle" class="e-title" required
                       value="<?= htmlspecialchars($article['title']) ?>"
                       placeholder="输入文章标题...">
                <div class="e-top-row">
                    <div class="e-top-left">
                        <span class="e-badge"><?= $id ? '编辑' : '新建' ?></span>
                        <span class="e-top-hint">Ctrl+S 保存 · Ctrl+P 预览 · 支持 HTML</span>
                    </div>
                    <div class="e-top-right">
                        <button type="button" class="ebtn" onclick="formatAll()" title="智能排版">✨ 排版</button>
                        <button type="button" class="ebtn" id="previewBtn" onclick="togglePV()">👁 预览</button>
                        <select name="status" id="estatus" class="e-select" onchange="updateStatusBadge()">
                            <option value="draft" <?= $article['status']=='draft'?'selected':'' ?>>草稿</option>
                            <option value="pending" <?= $article['status']=='pending'?'selected':'' ?>>待审核</option>
                            <option value="published" <?= $article['status']=='published'?'selected':'' ?>>发布</option>
                            <option value="archived" <?= $article['status']=='archived'?'selected':'' ?>>归档</option>
                        </select>
                    </div>
                </div>
            </div>

            <?php if ($error): ?><div class="alert alert-error" style="margin-bottom:16px"><?= $error ?></div><?php endif; ?>

            <!-- 双栏编辑区 -->
            <div class="e-body">
                <div class="e-editor" id="editorPane">
                    <form method="post" enctype="multipart/form-data" id="articleForm">
                        <?= csrfField() ?>
                        <div class="e-toolbar">
                            <button type="button" class="etb" onclick="ins('h2','标题')">H2</button>
                            <button type="button" class="etb" onclick="ins('h3','子标题')">H3</button>
                            <span class="etb-gap"></span>
                            <button type="button" class="etb" onclick="ins('strong','粗体')"><b>B</b></button>
                            <button type="button" class="etb" onclick="ins('code','代码')">&lt;/&gt;</button>
                            <button type="button" class="etb" onclick="ins('a','链接','link')">🔗</button>
                            <span class="etb-gap"></span>
                            <button type="button" class="etb" onclick="wrapBlock('pre')">{ }</button>
                            <button type="button" class="etb" onclick="wrapBlock('quote')">❝</button>
                            <button type="button" class="etb" onclick="wrapBlock('warn')">⚠</button>
                            <span class="etb-gap"></span>
                            <button type="button" class="etb" onclick="insImg()">🖼</button>
                        </div>
                        <textarea name="content" id="econtent" class="e-textarea"
                                  placeholder="开始写作...&#10;&#10;你可以粘贴纯文本，然后点击 ✨ 排版 自动格式化"><?= htmlspecialchars($article['content']) ?></textarea>
                    </form>
                </div>

                <div class="e-preview" id="previewPane" style="display:none">
                    <div class="e-preview-inner article-content" id="previewContent"></div>
                </div>
            </div>

            <!-- 底部操作栏 -->
            <div class="e-foot">
                <button type="submit" form="articleForm" class="btn btn-primary"><?= $id ? '保存修改' : '发布文章' ?></button>
                <a href="/myweb/admin/articles.php" class="btn">取消</a>
                <span class="e-foot-status" id="statusText"></span>
            </div>
        </div>

        <!-- 右侧设置 -->
        <div class="e-sidebar">
            <div class="es-card">
                <div class="es-card-title">分类</div>
                <select name="category_id" form="articleForm" class="e-select" style="width:100%">
                    <option value="">无分类</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id']==$article['category_id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="es-card">
                <div class="es-card-title">标签</div>
                <input type="text" id="tagInput" placeholder="空格或逗号分隔"
                       value="<?= htmlspecialchars(implode(', ', array_column($articleTags, 'name'))) ?>"
                       style="width:100%;padding:8px 10px;background:var(--gray-900);border:1px solid var(--gray-700);border-radius:6px;color:var(--gray-200);font-size:0.85rem">
                <div id="tagsHidden"></div>
                <div id="tagsChips" class="tag-chips" style="margin-top:6px"></div>
                <?php if ($allTags): ?>
                <div class="tag-suggestions" style="margin-top:6px">
                    <?php foreach ($allTags as $t): ?>
                    <span class="tag tag-suggest" onclick="addTag('<?= htmlspecialchars($t['name'], ENT_QUOTES) ?>')"><?= htmlspecialchars($t['name']) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="es-card">
                <div class="es-card-title">封面</div>
                <input type="file" name="cover_image" form="articleForm" accept="image/*" style="font-size:0.82rem;width:100%">
                <?php if ($article['cover_image']): ?>
                <img src="/myweb/<?= $article['cover_image'] ?>" style="max-width:100%;margin-top:8px;border-radius:8px">
                <?php endif; ?>
            </div>

            <div class="es-card">
                <div class="es-card-title">摘要</div>
                <textarea name="summary" form="articleForm" rows="2" placeholder="可选"
                          style="width:100%;padding:8px 10px;background:var(--gray-900);border:1px solid var(--gray-700);border-radius:6px;color:var(--gray-200);font-size:0.85rem;resize:vertical"><?= htmlspecialchars($article['summary']) ?></textarea>
            </div>

            <div class="es-card">
                <div class="es-card-title">🤖 AI</div>
                <div class="ai-xs">
                    <button class="ai-x" onclick="aiAct('summarize')">📝 摘要</button>
                    <button class="ai-x" onclick="aiAct('polish')">✨ 润色</button>
                    <button class="ai-x" onclick="aiAct('suggest_title')">🏆 标题</button>
                    <button class="ai-x" onclick="aiExpand()">✍️ 续写</button>
                    <button class="ai-x ai-x-go" onclick="showGen()">🌱 生成全文</button>
                </div>
                <div id="aiLoading" style="display:none;padding:6px 0;color:var(--gray-500);font-size:0.8rem">AI 思考中…</div>
                <div id="aiResult" style="display:none;margin-top:6px;padding:8px;background:var(--gray-900);border:1px solid var(--gray-700);border-radius:6px;max-height:180px;overflow-y:auto">
                    <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                        <span id="aiLabel" style="font-size:0.75rem;color:var(--accent-light);font-weight:600"></span>
                        <button onclick="aiUse()" style="padding:2px 8px;font-size:0.7rem;border:1px solid var(--accent);background:transparent;color:var(--accent-light);border-radius:4px;cursor:pointer">使用</button>
                    </div>
                    <div id="aiContent" style="font-size:0.78rem;color:var(--gray-300);white-space:pre-wrap"></div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
const ta = document.getElementById('econtent');
const title = document.getElementById('etitle');
const pv = document.getElementById('previewPane');
const pvContent = document.getElementById('previewContent');
let isPV = false;
let aiResult = '', aiAction = '';

// ===== 生成预览内容 =====
function updatePreview() {
    const html = ta.value || '<p style="color:var(--gray-500);text-align:center;padding:40px">暂无内容</p>';
    pvContent.innerHTML = html;
    pvContent.querySelectorAll('pre').forEach(p => {
        if (!p.hasAttribute('data-lang')) {
            const t = p.textContent.slice(0,200);
            if (/\b(npm |git |curl |sudo |export |\.\/|brew )/.test(t)) p.setAttribute('data-lang','BASH');
            else if (/^\$env:/m.test(t)) p.setAttribute('data-lang','PowerShell');
            else if (/\b(set |dir |copy |del |mkdir )/i.test(t)) p.setAttribute('data-lang','CMD');
            else p.setAttribute('data-lang','CODE');
        }
    });
}

// ===== 预览切换 =====
function togglePV() {
    isPV = !isPV;
    document.getElementById('editorPane').style.display = isPV ? 'none' : '';
    pv.style.display = isPV ? '' : 'none';
    document.getElementById('previewBtn').textContent = isPV ? '✏ 编辑' : '👁 预览';
    document.getElementById('previewBtn').classList.toggle('ebtn-on', isPV);
    if (isPV) updatePreview();
}

// ===== 插入标签 =====
function ins(tag, placeholder, type) {
    const s = ta.selectionStart, e = ta.selectionEnd;
    let sel = ta.value.substring(s, e);
    if (type === 'link') {
        const url = prompt('链接:', 'https://'); if (!url) return;
        sel = sel || url;
        const h = '<a href="'+url+'" target="_blank">'+sel+'</a>';
        ta.value = ta.value.substring(0,s) + h + ta.value.substring(e);
        ta.focus(); return;
    }
    sel = sel || placeholder;
    const h = '<'+tag+'>'+sel+'</'+tag+'>';
    ta.value = ta.value.substring(0,s) + h + ta.value.substring(e);
    ta.focus();
    ta.setSelectionRange(s + ('<'+tag+'>').length, s + ('<'+tag+'>').length + sel.length);
}

function wrapBlock(type) {
    const s = ta.selectionStart, e = ta.selectionEnd;
    let sel = ta.value.substring(s, e) || '内容';
    let h;
    if (type === 'pre') h = '\n<pre><code>'+sel+'</code></pre>\n';
    else if (type === 'quote') h = '\n<blockquote><p>'+sel+'</p></blockquote>\n';
    else if (type === 'warn') h = '\n<p class="article-warn">'+sel+'</p>\n';
    ta.value = ta.value.substring(0,s) + h + ta.value.substring(e);
    ta.focus();
}

function insImg() {
    const url = prompt('图片URL:', 'https://');
    if (!url) return;
    ta.value += '\n<img src="'+url+'" alt="">\n';
}

// ===== 智能排版 =====
function formatAll() {
    if (!ta.value.trim()) return;
    const b = document.querySelector('.ebtn');
    const orig = b.textContent;
    b.textContent = '⏳'; b.disabled = true;
    fetch('/myweb/ai_format.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'content='+encodeURIComponent(ta.value)+'&title='+encodeURIComponent(title.value)+'&csrf_token='+document.querySelector('[name=csrf_token]').value
    })
    .then(r=>r.json())
    .then(r=>{if(r.success&&r.html)ta.value=r.html;b.textContent='✓';})
    .catch(()=>b.textContent='✗')
    .finally(()=>setTimeout(()=>{b.textContent=orig;b.disabled=false;},2000));
}

// ===== AI辅助 =====
function aiAct(action) {
    if (!ta.value.trim()) return;
    aiAction = action;
    document.getElementById('aiLoading').style.display='block';
    document.getElementById('aiResult').style.display='none';
    document.getElementById('aiLabel').textContent = {summarize:'📝 摘要',polish:'✨ 润色',suggest_title:'🏆 标题'}[action]||action;
    fetch('/myweb/admin/ai_helper.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action='+action+'&content='+encodeURIComponent(ta.value)+'&title='+encodeURIComponent(title.value)})
    .then(r=>r.json()).then(r=>{document.getElementById('aiLoading').style.display='none';if(r.success){aiResult=r.data;document.getElementById('aiContent').textContent=r.data;document.getElementById('aiResult').style.display='block';}})
    .catch(()=>document.getElementById('aiLoading').style.display='none');
}
function aiUse() {
    if (aiAction==='summarize') document.querySelector('[name=summary]').value=aiResult;
    else if (aiAction==='suggest_title'){const ts=aiResult.split('\n').filter(t=>t.trim());const c=prompt('选标题:\n'+ts.map((t,i)=>(i+1)+'. '+t.replace(/^\d+[.、\s)]*\s*/,'')).join('\n'),'1');if(c){const i=parseInt(c)-1;if(i>=0&&i<ts.length)title.value=ts[i].replace(/^\d+[.、\s)]*\s*/,'');}}
    else {const p=ta.selectionStart;ta.value=ta.value.slice(0,p)+'\n'+aiResult+'\n'+ta.value.slice(p);}
    document.getElementById('aiResult').style.display='none';
}
function aiExpand(){if(!ta.value.trim())return;const c=prompt('长度: 1.短 2.中 3.长','2');if(!c)return;const m={'1':'short','2':'medium','3':'long'};aiAction='expand';document.getElementById('aiLoading').style.display='block';document.getElementById('aiResult').style.display='none';document.getElementById('aiLabel').textContent='✍️ 续写';fetch('/myweb/admin/ai_helper.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=expand&content='+encodeURIComponent(ta.value)+'&length='+(m[c]||'medium')}).then(r=>r.json()).then(r=>{document.getElementById('aiLoading').style.display='none';if(r.success){aiResult=r.data;document.getElementById('aiContent').textContent=r.data;document.getElementById('aiResult').style.display='block';}}).catch(()=>document.getElementById('aiLoading').style.display='none');}
function showGen(){const t=prompt('主题:','');if(!t)return;const s=prompt('风格: 1.通用 2.正式 3.口语 4.教程','1');if(!s)return;const m={'1':'general','2':'formal','3':'casual','4':'tech'};aiAction='generate';document.getElementById('aiLoading').style.display='block';document.getElementById('aiResult').style.display='none';document.getElementById('aiLabel').textContent='🌱 生成';fetch('/myweb/admin/ai_helper.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=generate&content='+encodeURIComponent(t)+'&style='+(m[s]||'general')}).then(r=>r.json()).then(r=>{document.getElementById('aiLoading').style.display='none';if(r.success){aiResult=r.data;document.getElementById('aiContent').textContent=typeof r.data==='object'?('标题:'+r.data.title+'\n\n'+r.data.content):r.data;document.getElementById('aiResult').style.display='block';}}).catch(()=>document.getElementById('aiLoading').style.display='none');}

// ===== 标签 =====
function tags() { return [...new Set(document.getElementById('tagInput').value.split(/[,\s]+/).map(t=>t.trim()).filter(t=>t))]; }
function renderTags() { const ts=tags(); document.getElementById('tagsChips').innerHTML=ts.map(t=>'<span class="tag tag-selected" onclick="rmTag(\''+t.replace(/'/g,"\\'")+'\')">'+t+' ✕</span>').join(''); document.getElementById('tagsHidden').innerHTML=ts.map(t=>'<input type="hidden" name="tags[]" value="'+t.replace(/"/g,'&quot;')+'" form="articleForm">').join(''); }
function addTag(n) { const i=document.getElementById('tagInput'), t=tags(); if(!t.includes(n)){t.push(n);i.value=t.join(', ');renderTags();} }
function rmTag(n) { const i=document.getElementById('tagInput'); i.value=tags().filter(t=>t!==n).join(', ');renderTags(); }
document.addEventListener('DOMContentLoaded',()=>{renderTags();document.getElementById('tagInput').addEventListener('input',renderTags);});

// ===== 快捷键 =====
document.addEventListener('keydown',function(e){
    if ((e.ctrlKey||e.metaKey) && e.key==='s') { e.preventDefault(); document.getElementById('statusText').textContent='已保存(Ctrl+S)'; setTimeout(()=>document.getElementById('statusText').textContent='',2000); }
    if ((e.ctrlKey||e.metaKey) && e.key==='p') { e.preventDefault(); togglePV(); }
});
</script>
<?php require_once '../includes/footer.php'; ?>
