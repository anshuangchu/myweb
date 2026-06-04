<?php
require_once '../includes/header.php';
if (!hasRole('super_admin', 'admin', 'editor')) redirect('/myweb/login.php?redirect=/myweb/admin/files.php');

$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$message = '';

// 上传处理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    verifyCsrf();
    $files = $_FILES['files'];
    $count = count($files['name']);
    $successCount = 0;
    $allowed = ['jpg','jpeg','png','gif','webp','ico','pdf','doc','docx','xls','xlsx','ppt','pptx','txt','zip','rar','7z','tar','gz','mp3','mp4','mov','avi'];

    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) continue;
        if ($files['size'][$i] > 50 * 1024 * 1024) continue;
        // 图片类额外验证 MIME 类型
        if (in_array($ext, ['jpg','jpeg','png','gif','webp','ico'])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $files['tmp_name'][$i]);
            finfo_close($finfo);
            $imageMime = ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml','image/x-icon','image/vnd.microsoft.icon'];
            if (!in_array($mime, $imageMime)) continue;
        }

        $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._\-\x{4e00}-\x{9fa5}]/u', '_', $files['name'][$i]);
        if (move_uploaded_file($files['tmp_name'][$i], $uploadDir . $filename)) {
            $successCount++;
        }
    }
    $message = "上传完成: $successCount/$count 个文件成功";
}

// 删除处理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    verifyCsrf();
    $file = basename($_POST['delete']);
    $path = $uploadDir . $file;
    if (file_exists($path) && is_file($path)) {
        unlink($path);
        $message = "文件已删除: $file";
    }
    redirect('/myweb/admin/files.php');
}

// 扫描文件（使用 helpers.php 中的共享函数）
$files = getUploadedFiles();
?>

<div class="admin-layout">
    <aside class="admin-sidebar"><?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?></aside>
    <main class="admin-main">
        <h2>文件管理</h2>

        <?php if ($message): ?><div class="alert alert-success"><?= $message ?></div><?php endif; ?>

        <!-- 上传区域 -->
        <div class="upload-zone" id="uploadZone">
            <div class="upload-icon">📁</div>
            <p>拖拽文件到此处，或点击选择文件</p>
            <p class="upload-hint">支持图片、文档、压缩包等，单文件最大 50MB</p>
            <form method="post" enctype="multipart/form-data" id="uploadForm">
                <?= csrfField() ?>
            <input type="file" name="files[]" id="fileInput" multiple style="display:none" onchange="document.getElementById('uploadForm').submit()">
                <button type="button" class="btn btn-primary" onclick="document.getElementById('fileInput').click()">选择文件上传</button>
            </form>
        </div>

        <!-- 文件统计 -->
        <div class="stats-grid" style="margin: 20px 0;">
            <div class="stat-card">
                <h3><?= count($files) ?></h3>
                <p>全部文件</p>
            </div>
            <div class="stat-card">
                <h3><?= count(array_filter($files, fn($f) => $f['is_image'])) ?></h3>
                <p>图片</p>
            </div>
            <div class="stat-card">
                <h3><?= formatSize(array_sum(array_column($files, 'size'))) ?></h3>
                <p>总大小</p>
            </div>
        </div>

        <!-- 文件列表 -->
        <?php if (empty($files)): ?>
            <div class="empty-state"><p>还没有上传文件</p></div>
        <?php else: ?>
        <div class="file-grid">
            <?php foreach ($files as $f): ?>
            <div class="file-item">
                <div class="file-preview">
                    <?php if ($f['is_image']): ?>
                        <img src="/myweb/uploads/<?= rawurlencode($f['name']) ?>" alt="">
                    <?php else: ?>
                        <div class="file-icon"><?php
                            $icons = ['pdf'=>'📄','zip'=>'📦','rar'=>'📦','7z'=>'📦','gz'=>'📦','doc'=>'📝','docx'=>'📝','txt'=>'📃','mp3'=>'🎵','mp4'=>'🎬','mov'=>'🎬'];
                            echo $icons[$f['ext']] ?? '📎';
                        ?></div>
                    <?php endif; ?>
                </div>
                <div class="file-info">
                    <div class="file-name" title="<?= htmlspecialchars($f['name']) ?>"><?= htmlspecialchars($f['name']) ?></div>
                    <div class="file-meta"><?= formatSize($f['size']) ?> · <?= date('Y-m-d', $f['time']) ?></div>
                </div>
                <div class="file-actions">
                    <button class="btn-sm" onclick="copyUrl('<?= '/myweb/uploads/' . rawurlencode($f['name']) ?>')">复制链接</button>
                    <?= deleteForm('/myweb/admin/files.php', 'delete', $f['name'], '删除') ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<script>
function copyUrl(url) {
    navigator.clipboard.writeText(url).then(() => {
        const btn = event.target;
        const text = btn.textContent;
        btn.textContent = '✅ 已复制';
        setTimeout(() => btn.textContent = text, 1500);
    });
}

// 拖拽上传
const zone = document.getElementById('uploadZone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.style.borderColor = '#58a6ff'; zone.style.background = 'rgba(88,166,255,0.1)'; });
zone.addEventListener('dragleave', () => { zone.style.borderColor = '#30363d'; zone.style.background = 'transparent'; });
zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.style.borderColor = '#30363d';
    zone.style.background = 'transparent';
    const dt = new DataTransfer();
    for (const file of e.dataTransfer.files) dt.items.add(file);
    document.getElementById('fileInput').files = dt.files;
    document.getElementById('uploadForm').submit();
});
</script>

<style>
.upload-zone {
    border: 2px dashed var(--border);
    border-radius: var(--radius-lg);
    padding: 48px 20px;
    text-align: center;
    transition: all .2s;
    cursor: pointer;
}
.upload-zone:hover { border-color: var(--accent); background: rgba(88,166,255,0.05); }
.upload-icon { font-size: 3rem; margin-bottom: 10px; }
.upload-hint { font-size: 0.85rem; color: var(--text-muted); margin-top: 8px; }
.file-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px;
    margin-top: 20px;
}
.file-item {
    background: var(--bg-body);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: all .25s ease;
}
.file-item:hover { border-color: rgba(88,166,255,0.15); transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,0.2); }
.file-preview {
    height: 140px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-hover);
    overflow: hidden;
}
.file-preview img { width: 100%; height: 100%; object-fit: cover; }
.file-icon { font-size: 3rem; }
.file-info { padding: 12px 14px; }
.file-name {
    font-size: 0.85rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 4px;
}
.file-meta { font-size: 0.75rem; color: var(--text-muted); }
.file-actions { padding: 10px 14px; border-top: 1px solid var(--border); display: flex; gap: 6px; }
</style>

<?php require_once '../includes/footer.php'; ?>
