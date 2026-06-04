<?php
$pageTitle = '资料';
require_once 'includes/header.php';

$pages = db()->query("SELECT * FROM pages WHERE status='published' ORDER BY created_at DESC")->fetchAll();

// 使用 helpers.php 中的共享函数
$files = getUploadedFiles();
?>

<style>
.pages-layout { max-width: 960px; margin: 0 auto; }

/* Header */
.pages-header { text-align: center; padding: 48px 20px 40px; }
.pages-header h1 {
    font-size: 2rem;
    font-weight: 800;
    letter-spacing: -0.03em;
    margin-bottom: 8px;
    background: linear-gradient(135deg, var(--text-primary) 0%, var(--accent) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.pages-header p { color: var(--text-secondary); font-size: 1.05rem; }

/* Tabs */
.pages-tabs {
    display: flex;
    gap: 4px;
    margin-bottom: 24px;
    padding: 4px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
}
.pages-tab {
    flex: 1;
    padding: 10px 20px;
    cursor: pointer;
    font-size: 0.88rem;
    font-weight: 500;
    color: var(--text-secondary);
    border: none;
    border-radius: 8px;
    background: transparent;
    transition: all .2s;
    user-select: none;
}
.pages-tab:hover { color: var(--text-primary); background: var(--bg-hover); }
.pages-tab.active { color: #fff; background: var(--accent); }
.pages-tab .count {
    display: inline-block;
    margin-left: 6px;
    padding: 0 8px;
    border-radius: 10px;
    font-size: 0.75rem;
    background: rgba(255,255,255,0.12);
    color: rgba(255,255,255,0.8);
    line-height: 1.6;
}
.pages-tab:not(.active) .count { background: var(--bg-hover); color: var(--text-muted); }
.panel { display: none; }
.panel.active { display: block; }

/* Document cards */
.doc-grid { display: grid; gap: 12px; }
.doc-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px 24px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    text-decoration: none;
    color: var(--text-primary);
    transition: all .25s ease;
}
.doc-card:hover {
    border-color: rgba(88,166,255,0.15);
    box-shadow: 0 4px 20px rgba(0,0,0,0.25);
    transform: translateY(-2px);
}
.doc-card .doc-icon {
    width: 44px; height: 44px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; flex-shrink: 0;
    background: linear-gradient(135deg, rgba(88,166,255,0.12), rgba(88,166,255,0.05));
}
.doc-card .doc-body { flex: 1; min-width: 0; }
.doc-card .doc-body .doc-title { font-weight: 600; font-size: 0.95rem; margin-bottom: 2px; }
.doc-card .doc-body .doc-meta { font-size: 0.8rem; color: var(--text-muted); }
.doc-card .doc-arrow { font-size: 1rem; color: var(--text-muted); flex-shrink: 0; transition: transform .2s; }
.doc-card:hover .doc-arrow { transform: translateX(4px); color: var(--accent); }

/* File grid */
.file-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
.file-item {
    display: block; background: var(--bg-card); border: 1px solid var(--border);
    border-radius: var(--radius-lg); overflow: hidden; text-decoration: none;
    color: var(--text-primary); transition: all .25s ease;
}
.file-item:hover { border-color: rgba(88,166,255,0.15); transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,0.25); }
.file-preview {
    height: 130px; display: flex; align-items: center; justify-content: center;
    background: var(--bg-hover); overflow: hidden; position: relative;
}
.file-preview img { width: 100%; height: 100%; object-fit: cover; }
.file-icon { font-size: 2.8rem; }
.file-ext-badge {
    position: absolute; top: 8px; right: 8px; padding: 2px 8px;
    border-radius: 4px; font-size: 0.7rem; font-weight: 600;
    background: rgba(0,0,0,0.5); color: #fff; text-transform: uppercase;
}
.file-info { padding: 12px 14px; }
.file-name { font-size: 0.88rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 4px; }
.file-meta { font-size: 0.75rem; color: var(--text-muted); }
.file-stats { color: var(--text-secondary); font-size: 0.88rem; margin-bottom: 16px; padding: 8px 4px; }
</style>

<div class="pages-layout">
    <nav class="breadcrumb">
        <a href="/myweb/">首页</a>
        <span class="sep">›</span>
        <span class="current">资料</span>
    </nav>
    <div class="pages-header">
        <h1>📁 资料</h1>
        <p>文档与共享文件</p>
    </div>

    <!-- Tabs -->
    <div class="pages-tabs">
        <button class="pages-tab active" data-tab="docs">
            📄 文档 <span class="count"><?= count($pages) ?></span>
        </button>
        <button class="pages-tab" data-tab="files">
            📎 文件 <span class="count"><?= count($files) ?></span>
        </button>
    </div>

    <!-- 文档面板 -->
    <div class="panel active" id="panel-docs">
        <?php if (empty($pages)): ?>
            <div class="empty-state">
                <div class="empty-icon">📄</div>
                <p>暂无文档</p>
            </div>
        <?php else: ?>
        <div class="doc-grid">
            <?php foreach ($pages as $p): ?>
            <a href="/myweb/page.php?slug=<?= urlencode($p['slug']) ?>" class="doc-card">
                <div class="doc-icon">📄</div>
                <div class="doc-body">
                    <div class="doc-title"><?= htmlspecialchars($p['title']) ?></div>
                    <div class="doc-meta"><?= date('Y-m-d', strtotime($p['created_at'])) ?></div>
                </div>
                <span class="doc-arrow">→</span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 文件面板 -->
    <div class="panel" id="panel-files">
        <?php if (empty($files)): ?>
            <div class="empty-state">
                <div class="empty-icon">📎</div>
                <p>暂无共享文件</p>
            </div>
        <?php else: ?>
            <div class="file-stats">共 <?= count($files) ?> 个文件，合计 <?= formatSize(array_sum(array_column($files, 'size'))) ?></div>
            <div class="file-grid">
                <?php foreach ($files as $f): ?>
                <?php $viewUrl = '/myweb/view.php?file=' . rawurlencode($f['name']); ?>
                <div class="file-item">
                    <a href="<?= $viewUrl ?>" class="file-preview">
                        <?php if ($f['is_image']): ?>
                            <img src="/myweb/uploads/<?= rawurlencode($f['name']) ?>" alt="">
                        <?php else: ?>
                            <div class="file-icon"><?php
                                $icons = ['pdf'=>'📄','zip'=>'📦','rar'=>'📦','7z'=>'📦','tar'=>'📦','gz'=>'📦','doc'=>'📝','docx'=>'📝','txt'=>'📃','mp3'=>'🎵','mp4'=>'🎬','xls'=>'📊','xlsx'=>'📊'];
                                echo $icons[$f['ext']] ?? '📎';
                            ?></div>
                            <span class="file-ext-badge"><?= strtoupper($f['ext']) ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="file-info">
                        <a href="<?= $viewUrl ?>" class="file-name"><?= htmlspecialchars($f['name']) ?></a>
                        <div class="file-meta"><?= formatSize($f['size']) ?> · <?= date('Y-m-d', $f['time']) ?></div>
                        <div style="margin-top:8px">
                            <a href="/myweb/uploads/<?= rawurlencode($f['name']) ?>" download="<?= htmlspecialchars($f['name']) ?>" class="btn-sm" style="background:var(--bg-hover);border:1px solid var(--border);color:var(--text-secondary);text-decoration:none;display:inline-flex;align-items:center;gap:4px">⬇ 下载</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.querySelectorAll('.pages-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.pages-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('panel-' + this.dataset.tab).classList.add('active');
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
