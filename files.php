<?php
$pageTitle = '文件浏览';
require_once 'includes/header.php';

$typeFilter = $_GET['type'] ?? 'all';
$searchQuery = trim($_GET['q'] ?? '');

// 文件分类映射
$categories = [
    'all'      => ['label' => '全部', 'icon' => '📁'],
    'image'    => ['label' => '图片', 'icon' => '🖼️', 'exts' => ['jpg','jpeg','png','gif','webp','ico']],
    'document' => ['label' => '文档', 'icon' => '📄', 'exts' => ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt']],
    'archive'  => ['label' => '压缩包', 'icon' => '📦', 'exts' => ['zip','rar','7z','tar','gz']],
    'media'    => ['label' => '音视频', 'icon' => '🎬', 'exts' => ['mp3','mp4','mov','avi']],
];

// 根据分类过滤扩展名
$allowedExts = null;
if ($typeFilter !== 'all' && isset($categories[$typeFilter])) {
    $allowedExts = $categories[$typeFilter]['exts'];
}

$files = getUploadedFiles($allowedExts ?? []);

// 按文件名搜索
if ($searchQuery) {
    $files = array_values(array_filter($files, fn($f) => mb_stripos($f['name'], $searchQuery) !== false));
}

// 分页
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24;
$totalFiles = count($files);
$totalPages = max(1, ceil($totalFiles / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$pageFiles = array_slice($files, $offset, $perPage);
?>
<style>
/* ===== 文件浏览页 ===== */
.files-page { max-width: 1200px; margin: 0 auto; padding: 24px 0; }
.files-header { text-align: center; padding: 32px 20px; }
.files-header h1 { font-size: 1.8rem; font-weight: 800; margin-bottom: 6px; background: linear-gradient(135deg, var(--text-primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
.files-header p { color: var(--text-secondary); font-size: 0.95rem; }

/* 搜索栏 */
.files-search { max-width: 480px; margin: 16px auto 24px; position: relative; }
.files-search input {
    width: 100%; padding: 10px 16px 10px 38px;
    border: 1px solid var(--border); border-radius: 8px;
    font-size: 0.9rem; background: var(--bg-card); color: var(--text-primary);
    transition: border .2s;
}
.files-search input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(88,166,255,0.12); }
.files-search .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }

/* 分类标签 */
.files-tabs { display: flex; gap: 6px; margin-bottom: 24px; flex-wrap: wrap; justify-content: center; }
.files-tab {
    padding: 7px 18px; border-radius: 20px; font-size: 0.85rem; font-weight: 500;
    border: 1px solid var(--border); background: var(--bg-card); color: var(--text-secondary);
    cursor: pointer; text-decoration: none; transition: all .2s; user-select: none;
}
.files-tab:hover { border-color: var(--accent); color: var(--text-primary); }
.files-tab.active { background: var(--accent); color: #fff; border-color: var(--accent); }
.files-tab .count { margin-left: 4px; font-size: 0.75rem; opacity: 0.7; }

/* 文件统计条 */
.files-stats { display: flex; gap: 12px; margin-bottom: 20px; padding: 12px 18px; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); flex-wrap: wrap; }
.files-stat { font-size: 0.85rem; color: var(--text-muted); }
.files-stat strong { color: var(--text-primary); font-weight: 600; }

/* 文件网格 */
.files-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px;
}
.file-card {
    background: var(--bg-card); border: 1px solid var(--border);
    border-radius: var(--radius-lg); overflow: hidden;
    transition: all .25s ease; display: flex; flex-direction: column;
}
.file-card:hover { border-color: rgba(88,166,255,0.15); transform: translateY(-2px); box-shadow: 0 4px 20px rgba(0,0,0,0.2); }

.file-card-preview {
    height: 150px; display: flex; align-items: center; justify-content: center;
    background: var(--bg-hover); overflow: hidden; position: relative; flex-shrink: 0;
}
.file-card-preview img { width: 100%; height: 100%; object-fit: cover; }
.file-card-icon { font-size: 3rem; }
.file-card-ext {
    position: absolute; top: 10px; right: 10px; padding: 2px 8px;
    border-radius: 4px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
    background: rgba(0,0,0,0.6); color: #fff; letter-spacing: 0.3px;
}

.file-card-body { padding: 14px 16px; flex: 1; display: flex; flex-direction: column; min-width: 0; }
.file-card-name {
    font-size: 0.88rem; font-weight: 500; white-space: nowrap; overflow: hidden;
    text-overflow: ellipsis; margin-bottom: 6px; color: var(--text-primary);
}
.file-card-meta { font-size: 0.78rem; color: var(--text-muted); margin-bottom: 12px; }
.file-card-actions { margin-top: auto; display: flex; gap: 6px; }
.file-card-actions .btn-sm { flex: 1; text-align: center; font-size: 0.8rem; }

.empty-files { text-align: center; padding: 80px 20px; color: var(--text-muted); }
.empty-files .icon { font-size: 3.5rem; margin-bottom: 16px; }

/* 文件列表视图 */
.view-toggle { display: flex; gap: 4px; margin-left: auto; }
.view-btn {
    padding: 6px 10px; border: 1px solid var(--border); border-radius: 6px;
    background: var(--bg-card); color: var(--text-muted); cursor: pointer; font-size: 0.85rem;
    line-height: 1; transition: all .2s;
}
.view-btn:hover { border-color: var(--accent); color: var(--text-primary); }
.view-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }

/* 列表视图 */
.files-list { display: flex; flex-direction: column; gap: 4px; }
.file-list-item {
    display: flex; align-items: center; gap: 14px; padding: 12px 16px;
    background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius);
    transition: background .15s;
}
.file-list-item:hover { background: var(--bg-hover); }
.file-list-icon { font-size: 1.5rem; flex-shrink: 0; width: 36px; text-align: center; }
.file-list-info { flex: 1; min-width: 0; }
.file-list-name { font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.file-list-meta { font-size: 0.78rem; color: var(--text-muted); }
.file-list-actions { flex-shrink: 0; display: flex; gap: 6px; }
</style>

<div class="files-page">
    <div class="files-header">
        <h1>📁 文件浏览</h1>
        <p>浏览和下载共享文件</p>
    </div>

    <!-- 搜索栏 -->
    <form class="files-search" method="get">
        <span class="search-icon">🔍</span>
        <input type="text" name="q" placeholder="搜索文件名..." value="<?= htmlspecialchars($searchQuery) ?>">
        <?php if ($typeFilter !== 'all'): ?>
        <input type="hidden" name="type" value="<?= htmlspecialchars($typeFilter) ?>">
        <?php endif; ?>
    </form>

    <!-- 分类标签 -->
    <div class="files-tabs">
        <?php foreach ($categories as $key => $cat): ?>
        <?php
            $exts = $cat['exts'] ?? null;
            $count = count(getUploadedFiles($exts ?? []));
        ?>
        <a href="/myweb/files.php?type=<?= $key ?><?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>"
           class="files-tab <?= $typeFilter === $key ? 'active' : '' ?>">
            <?= $cat['icon'] ?> <?= $cat['label'] ?> <span class="count"><?= $count ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- 统计 + 视图切换 -->
    <div class="files-stats">
        <span class="files-stat">共 <strong><?= $totalFiles ?></strong> 个文件</span>
        <span class="files-stat">合计 <strong><?= formatSize(array_sum(array_column($files, 'size'))) ?></strong></span>
        <span class="files-stat">当前页 <strong><?= count($pageFiles) ?></strong> 个文件</span>
        <div class="view-toggle">
            <button class="view-btn active" id="viewGridBtn" onclick="switchView('grid')" title="网格视图">▦</button>
            <button class="view-btn" id="viewListBtn" onclick="switchView('list')" title="列表视图">☰</button>
        </div>
    </div>

    <?php if (empty($pageFiles)): ?>
        <div class="empty-files">
            <div class="icon">📂</div>
            <p><?= $searchQuery ? '没有匹配的文件' : '暂无文件' ?></p>
        </div>
    <?php else: ?>

    <!-- 网格视图 -->
    <div id="viewGrid" class="files-grid">
        <?php foreach ($pageFiles as $f): ?>
        <?php $viewUrl = '/myweb/view.php?file=' . rawurlencode($f['name']); ?>
        <div class="file-card">
            <a href="<?= $viewUrl ?>" class="file-card-preview" style="display:flex;text-decoration:none;color:inherit">
                <?php if ($f['is_image']): ?>
                    <img src="/myweb/uploads/<?= rawurlencode($f['name']) ?>" alt="" loading="lazy">
                <?php else: ?>
                    <div class="file-card-icon"><?php
                        $iconMap = ['pdf'=>'📄','doc'=>'📝','docx'=>'📝','xls'=>'📊','xlsx'=>'📊','ppt'=>'📽️','pptx'=>'📽️','txt'=>'📃','zip'=>'📦','rar'=>'📦','7z'=>'📦','tar'=>'📦','gz'=>'📦','mp3'=>'🎵','mp4'=>'🎬','mov'=>'🎬','avi'=>'🎬'];
                        echo $iconMap[$f['ext']] ?? '📎';
                    ?></div>
                <?php endif; ?>
                <span class="file-card-ext"><?= strtoupper($f['ext']) ?></span>
            </a>
            <div class="file-card-body">
                <a href="<?= $viewUrl ?>" class="file-card-name" style="text-decoration:none;color:inherit" title="<?= htmlspecialchars($f['name']) ?>"><?= htmlspecialchars($f['name']) ?></a>
                <div class="file-card-meta"><?= formatSize($f['size']) ?> · <?= date('Y-m-d', $f['time']) ?></div>
                <div class="file-card-actions">
                    <a href="<?= $viewUrl ?>" class="btn-sm">👁️ 查看</a>
                    <a href="/myweb/uploads/<?= rawurlencode($f['name']) ?>" download="<?= htmlspecialchars($f['name']) ?>" class="btn-sm" style="background:var(--bg-hover);border:1px solid var(--border);color:var(--text-secondary)">⬇ 下载</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 列表视图 -->
    <div id="viewList" class="files-list" style="display:none">
        <?php foreach ($pageFiles as $f): ?>
        <?php $viewUrl = '/myweb/view.php?file=' . rawurlencode($f['name']); ?>
        <div class="file-list-item" style="cursor:pointer" onclick="location.href='<?= $viewUrl ?>'">
            <div class="file-list-icon"><?php
                $iconMap = ['pdf'=>'📄','doc'=>'📝','docx'=>'📝','xls'=>'📊','xlsx'=>'📊','ppt'=>'📽️','pptx'=>'📽️','txt'=>'📃','zip'=>'📦','rar'=>'📦','7z'=>'📦','tar'=>'📦','gz'=>'📦','mp3'=>'🎵','mp4'=>'🎬','mov'=>'🎬','avi'=>'🎬'];
                echo $f['is_image'] ? '🖼️' : ($iconMap[$f['ext']] ?? '📎');
            ?></div>
            <div class="file-list-info">
                <div class="file-list-name"><?= htmlspecialchars($f['name']) ?></div>
                <div class="file-list-meta"><?= formatSize($f['size']) ?> · <?= date('Y-m-d', $f['time']) ?></div>
            </div>
            <div class="file-list-actions">
                <a href="<?= $viewUrl ?>" class="btn-sm" onclick="event.stopPropagation()">👁️ 查看</a>
                <a href="/myweb/uploads/<?= rawurlencode($f['name']) ?>" download="<?= htmlspecialchars($f['name']) ?>" class="btn-sm" style="background:var(--bg-hover);border:1px solid var(--border);color:var(--text-secondary)" onclick="event.stopPropagation()">⬇ 下载</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?= renderPagination($page, $totalPages, '/myweb/files.php?type=' . urlencode($typeFilter) . ($searchQuery ? '&q=' . urlencode($searchQuery) : '') . '&page=%d') ?>
    <?php endif; ?>
</div>

<script>
function switchView(view) {
    const grid = document.getElementById('viewGrid');
    const list = document.getElementById('viewList');
    const gridBtn = document.getElementById('viewGridBtn');
    const listBtn = document.getElementById('viewListBtn');
    if (view === 'grid') {
        grid.style.display = 'grid';
        list.style.display = 'none';
        gridBtn.classList.add('active');
        listBtn.classList.remove('active');
    } else {
        grid.style.display = 'none';
        list.style.display = 'flex';
        listBtn.classList.add('active');
        gridBtn.classList.remove('active');
    }
    localStorage.setItem('filesView', view);
}
document.addEventListener('DOMContentLoaded', function() {
    const saved = localStorage.getItem('filesView');
    if (saved === 'list') switchView('list');
});
</script>

<?php require_once 'includes/footer.php'; ?>
