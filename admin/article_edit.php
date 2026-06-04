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
        if ($_FILES['cover_image']['size'] > 5 * 1024 * 1024) { $error = '封面图片不能超过 5MB'; }
        else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['cover_image']['tmp_name']); finfo_close($finfo);
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
                foreach ($tagNames as $n) { if ($n === '') continue; $ts->execute([$n]); $at->execute([$id, $n]); }
            }
        }
        redirect('/myweb/admin/articles.php');
    }
}
?>
<div class="admin-layout">
    <aside class="admin-sidebar"><?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?></aside>
    <main class="admin-main" style="padding:24px 32px">
        <form method="post" enctype="multipart/form-data" id="articleForm">
        <?= csrfField() ?>
        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

        <!-- 标题 -->
        <input type="text" name="title" class="ed-title" required
               value="<?= htmlspecialchars($article['title']) ?>" placeholder="输入文章标题...">

        <!-- 菜单栏 -->
        <div class="ed-ribbon">
            <div class="ed-group">
                <button type="button" class="ed-btn" onclick="wrap('h2')" title="二级标题"><b>H2</b></button>
                <button type="button" class="ed-btn" onclick="wrap('h3')" title="三级标题"><b>H3</b></button>
                <button type="button" class="ed-btn" onclick="wrap('h4')" title="四级标题">H4</button>
            </div>
            <div class="ed-group">
                <button type="button" class="ed-btn" onclick="wrap('strong')" title="粗体"><b>B</b></button>
                <button type="button" class="ed-btn" onclick="wrap('em')" title="斜体"><i>I</i></button>
                <button type="button" class="ed-btn" onclick="wrap('u')" title="下划线"><u>U</u></button>
                <button type="button" class="ed-btn" onclick="wrap('code')" title="行内代码">&lt;/&gt;</button>
            </div>
            <div class="ed-group">
                <button type="button" class="ed-btn" onclick="insertBlock('pre')" title="代码块">◻ 代码块</button>
                <button type="button" class="ed-btn" onclick="insertBlock('blockquote')" title="引用">❝ 引用</button>
                <button type="button" class="ed-btn" onclick="insertWarn()" title="警告提示">⚠ 警告</button>
            </div>
            <div class="ed-group">
                <button type="button" class="ed-btn" onclick="insertList('ul')" title="无序列表">• 列表</button>
                <button type="button" class="ed-btn" onclick="insertList('ol')" title="有序列表">1. 列表</button>
            </div>
            <div class="ed-group">
                <button type="button" class="ed-btn" onclick="insertLink()" title="插入链接">🔗 链接</button>
                <button type="button" class="ed-btn" onclick="insertImg()" title="插入图片">🖼 图片</button>
                <button type="button" class="ed-btn" onclick="insertHR()" title="分隔线">—</button>
            </div>
            <div class="ed-group" style="margin-left:auto">
                <button type="button" class="ed-btn ed-btn-accent" onclick="doFormat()" title="智能排版">✨ 排版</button>
            </div>
        </div>

        <!-- 编辑区 -->
        <div class="ed-area">
            <textarea name="content" id="editor" class="ed-textarea"
                      placeholder="开始写作... 使用上方工具栏格式化文本。所写即所见。"><?= htmlspecialchars($article['content']) ?></textarea>
            <div class="ed-preview article-content" id="preview"></div>
        </div>

        <!-- 底部操作 -->
        <div class="ed-foot">
            <button type="submit" class="btn btn-primary"><?= $id ? '保存修改' : '发布文章' ?></button>
            <a href="/myweb/admin/articles.php" class="btn">取消</a>
            <span class="ed-foot-status">Ctrl+S · 选中文字点击工具栏按钮格式化</span>
        </div>

        <!-- 右侧设置 -->
        <div class="ed-sidebar">
            <div class="es-card">
                <div class="es-card-title">发布设置</div>
                <div class="form-group"><label>状态</label>
                <select name="status" class="e-select" style="width:100%">
                    <option value="draft" <?= $article['status']=='draft'?'selected':'' ?>>草稿</option>
                    <option value="pending" <?= $article['status']=='pending'?'selected':'' ?>>待审核</option>
                    <option value="published" <?= $article['status']=='published'?'selected':'' ?>>发布</option>
                    <option value="archived" <?= $article['status']=='archived'?'selected':'' ?>>归档</option>
                </select></div>
                <div class="form-group"><label>分类</label>
                <select name="category_id" class="e-select" style="width:100%">
                    <option value="">无分类</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id']==$article['category_id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            </div>

            <div class="es-card">
                <div class="es-card-title">标签</div>
                <input type="text" id="tagInput" placeholder="空格或逗号分隔" value="<?= htmlspecialchars(implode(', ', array_column($articleTags, 'name'))) ?>" style="width:100%;padding:8px;background:var(--gray-900);border:1px solid var(--gray-700);border-radius:6px;color:var(--gray-200);font-size:0.85rem">
                <div id="tagsHidden"></div>
                <div id="tagsChips" class="tag-chips" style="margin-top:6px"></div>
                <?php if ($allTags): ?><div class="tag-suggestions" style="margin-top:6px"><?php foreach ($allTags as $t): ?><span class="tag tag-suggest" onclick="addTag('<?= htmlspecialchars($t['name'], ENT_QUOTES) ?>')"><?= htmlspecialchars($t['name']) ?></span><?php endforeach; ?></div><?php endif; ?>
            </div>

            <div class="es-card">
                <div class="es-card-title">封面图片</div>
                <input type="file" name="cover_image" accept="image/*" style="font-size:0.82rem;width:100%">
                <?php if ($article['cover_image']): ?><img src="/myweb/<?= $article['cover_image'] ?>" style="max-width:100%;margin-top:8px;border-radius:8px"><?php endif; ?>
            </div>

            <div class="es-card">
                <div class="es-card-title">摘要</div>
                <textarea name="summary" rows="2" placeholder="可选" style="width:100%;padding:8px;background:var(--gray-900);border:1px solid var(--gray-700);border-radius:6px;color:var(--gray-200);font-size:0.85rem;resize:vertical"><?= htmlspecialchars($article['summary']) ?></textarea>
            </div>
        </div>
        </form>
    </main>
</div>

<script>
const ta = document.getElementById('editor');
const pv = document.getElementById('preview');

// ===== 实时同步预览 =====
function sync() {
    pv.innerHTML = ta.value || '<p style="color:var(--gray-500);text-align:center;padding:40px">预览区</p>';
    pv.querySelectorAll('pre').forEach(p => {
        if (!p.hasAttribute('data-lang')) p.setAttribute('data-lang','CODE');
    });
}
ta.addEventListener('input', sync);
sync();

// ===== 工具栏函数 =====
function wrap(tag) {
    const s = ta.selectionStart, e = ta.selectionEnd;
    const sel = ta.value.substring(s, e) || tag.toUpperCase();
    const html = '<' + tag + '>' + sel + '</' + tag + '>';
    ta.value = ta.value.substring(0, s) + html + ta.value.substring(e);
    ta.focus();
    ta.setSelectionRange(s + tag.length + 2, s + tag.length + 2 + sel.length);
    sync();
}

function insertBlock(type) {
    const s = ta.selectionStart, e = ta.selectionEnd;
    let sel = ta.value.substring(s, e) || '内容';
    let html;
    if (type === 'pre') html = '\n<pre><code>' + sel + '</code></pre>\n';
    else if (type === 'blockquote') html = '\n<blockquote><p>' + sel + '</p></blockquote>\n';
    ta.value = ta.value.substring(0, s) + html + ta.value.substring(e);
    ta.focus();
    sync();
}

function insertWarn() {
    const s = ta.selectionStart, e = ta.selectionEnd;
    const sel = ta.value.substring(s, e) || '注意内容';
    ta.value = ta.value.substring(0, s) + '\n<p class="article-warn">' + sel + '</p>\n' + ta.value.substring(e);
    ta.focus(); sync();
}

function insertList(type) {
    const s = ta.selectionStart;
    const tag = type === 'ol' ? 'ol' : 'ul';
    const html = '\n<' + tag + '>\n  <li>项目一</li>\n  <li>项目二</li>\n  <li>项目三</li>\n</' + tag + '>\n';
    ta.value = ta.value.substring(0, s) + html + ta.value.substring(s);
    ta.focus(); sync();
}

function insertLink() {
    const url = prompt('链接地址:', 'https://');
    if (!url) return;
    const s = ta.selectionStart, e = ta.selectionEnd;
    const sel = ta.value.substring(s, e) || url;
    ta.value = ta.value.substring(0, s) + '<a href="' + url + '" target="_blank">' + sel + '</a>' + ta.value.substring(e);
    ta.focus(); sync();
}

function insertImg() {
    const url = prompt('图片地址:', 'https://');
    if (!url) return;
    const s = ta.selectionStart;
    ta.value = ta.value.substring(0, s) + '<img src="' + url + '" alt="" style="max-width:100%">\n' + ta.value.substring(s);
    ta.focus(); sync();
}

function insertHR() {
    const s = ta.selectionStart;
    ta.value = ta.value.substring(0, s) + '\n<hr>\n' + ta.value.substring(s);
    ta.focus(); sync();
}

function doFormat() {
    if (!ta.value.trim()) return;
    fetch('/myweb/ai_format.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'content='+encodeURIComponent(ta.value)+'&csrf_token='+document.querySelector('[name=csrf_token]').value
    })
    .then(r=>r.json()).then(r=>{if(r.success){ta.value=r.html;sync();}}).catch(()=>{});
}

// ===== 标签 =====
function tags() { return [...new Set(document.getElementById('tagInput').value.split(/[,\s]+/).map(t=>t.trim()).filter(t=>t))]; }
function renderTags() { const ts=tags(); document.getElementById('tagsChips').innerHTML=ts.map(t=>'<span class="tag tag-selected" onclick="rmTag(\''+t.replace(/'/g,"\\'")+'\')">'+t+' ✕</span>').join(''); document.getElementById('tagsHidden').innerHTML=ts.map(t=>'<input type="hidden" name="tags[]" value="'+t.replace(/"/g,'&quot;')+'" form="articleForm">').join(''); }
function addTag(n) { const i=document.getElementById('tagInput'), t=tags(); if(!t.includes(n)){t.push(n);i.value=t.join(', ');renderTags();} }
function rmTag(n) { const i=document.getElementById('tagInput'); i.value=tags().filter(t=>t!==n).join(', ');renderTags(); }
document.addEventListener('DOMContentLoaded',()=>{renderTags();document.getElementById('tagInput').addEventListener('input',renderTags);});

// ===== 快捷键 =====
document.addEventListener('keydown',function(e){
    if ((e.ctrlKey||e.metaKey) && e.key==='s') { e.preventDefault(); }
    if ((e.ctrlKey||e.metaKey) && e.key==='b') { e.preventDefault(); wrap('strong'); }
    if ((e.ctrlKey||e.metaKey) && e.key==='i') { e.preventDefault(); wrap('em'); }
});
</script>
<?php require_once '../includes/footer.php'; ?>
