<?php
require_once '../includes/header.php';
require_once __DIR__ . '/../includes/file_service.php';

if (!hasRole('super_admin', 'admin', 'editor')) redirect('/myweb/login.php?redirect=/myweb/admin/files.php');

$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$message = '';
$msgType = 'success';

// ===== 上传 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    verifyCsrf();
    $files = $_FILES['files'];
    $count = count($files['name']);
    $successCount = 0;
    $allowedExts = FileService::getAllowedExts();

    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts, true)) continue;
        if ($files['size'][$i] > 50 * 1024 * 1024) continue;

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $files['tmp_name'][$i]);
        finfo_close($finfo);

        $validMimes = [
            'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
            'gif' => ['image/gif'], 'webp' => ['image/webp'], 'ico' => ['image/x-icon','image/vnd.microsoft.icon'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'], 'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls' => ['application/vnd.ms-excel'], 'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'ppt' => ['application/vnd.ms-powerpoint'], 'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'txt' => ['text/plain'], 'zip' => ['application/zip','application/x-zip-compressed'],
            'rar' => ['application/x-rar-compressed','application/vnd.rar'], '7z' => ['application/x-7z-compressed'],
            'tar' => ['application/x-tar'], 'gz' => ['application/gzip','application/x-gzip'],
            'mp3' => ['audio/mpeg'], 'mp4' => ['video/mp4'], 'mov' => ['video/quicktime'], 'avi' => ['video/x-msvideo'],
        ];
        if (isset($validMimes[$ext]) && !in_array($mime, $validMimes[$ext], true)) continue;

        $filename = FileService::generateSafeFilename($files['name'][$i], $ext);
        if (move_uploaded_file($files['tmp_name'][$i], $uploadDir . $filename)) $successCount++;
    }
    $message = "上传完成: $successCount/$count 个文件成功";
}

// ===== 删除 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    verifyCsrf();
    $file = basename($_POST['delete']);
    $path = $uploadDir . $file;
    if (file_exists($path) && is_file($path)) {
        unlink($path);
        $message = "已删除: " . htmlspecialchars($file);
    }
}

// ===== 重命名 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_old'])) {
    verifyCsrf();
    $oldName = basename($_POST['rename_old']);
    $newName = trim($_POST['rename_new'] ?? '');
    if ($newName && $oldName !== $newName) {
        // 确保扩展名不变
        $oldExt = strtolower(pathinfo($oldName, PATHINFO_EXTENSION));
        $newExt = strtolower(pathinfo($newName, PATHINFO_EXTENSION));
        if ($newExt !== $oldExt) {
            $newName = pathinfo($newName, PATHINFO_FILENAME) . '.' . $oldExt;
        }
        // 安全过滤文件名
        $newName = preg_replace('/[\/\\\\:\*\?"<>|]/', '_', $newName);
        if (FileService::renameFile($oldName, $newName)) {
            $message = "已重命名为: " . htmlspecialchars($newName);
        } else {
            $message = "重命名失败（目标文件已存在或权限不足）";
            $msgType = 'error';
        }
    }
}

// ===== 编辑文本内容 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_file'])) {
    verifyCsrf();
    $fileName = basename($_POST['edit_file']);
    $content = $_POST['edit_content'] ?? '';
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (FileService::isTextEditable($ext)) {
        if (FileService::saveTextFile($fileName, $content)) {
            $message = "已保存: " . htmlspecialchars($fileName);
        } else {
            $message = "保存失败: " . htmlspecialchars($fileName);
            $msgType = 'error';
        }
    }
}

$fileData = FileService::getFiles();
$files = $fileData['files'];
$stats = $fileData['stats'];
?>

<div class="admin-layout">
    <aside class="admin-sidebar"><?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?></aside>
    <main class="admin-main">
        <h2>文件管理</h2>

        <?php if ($message): ?>
        <div class="alert alert-<?= $msgType ?>"><?= $message ?></div>
        <?php endif; ?>

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
        <div class="stats-grid" style="margin:20px 0">
            <div class="stat-card"><h3><?= $stats['total'] ?></h3><p>全部文件</p></div>
            <div class="stat-card"><h3><?= count(array_filter($files, fn($f) => $f['is_image'])) ?></h3><p>图片</p></div>
            <div class="stat-card"><h3><?= formatSize($stats['totalSize']) ?></h3><p>总大小</p></div>
        </div>

        <?php if (empty($files)): ?>
            <div class="empty-state"><p>还没有上传文件</p></div>
        <?php else: ?>
        <div class="admin-file-grid">
            <?php foreach ($files as $f): ?>
            <?php
                $rawName = $f['name'];
                $rawExt  = $f['ext'];
                $editable = FileService::isTextEditable($rawExt);
            ?>
            <div class="admin-file-item">
                <div class="admin-file-preview">
                    <?php if ($f['is_image']): ?>
                        <img src="/myweb/uploads/<?= rawurlencode($rawName) ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <div class="admin-file-icon"><?= $f['icon'] ?></div>
                    <?php endif; ?>
                </div>
                <div class="admin-file-info">
                    <div class="admin-file-name" title="<?= htmlspecialchars($rawName) ?>"><?= htmlspecialchars($rawName) ?></div>
                    <div class="admin-file-meta"><?= formatSize($f['size']) ?> · <?= date('Y-m-d', $f['time']) ?></div>
                </div>
                <div class="admin-file-actions">
                    <button class="btn-sm" onclick="copyUrl('<?= '/myweb/uploads/' . rawurlencode($rawName) ?>')">复制</button>
                    <button class="btn-sm" onclick="openRename('<?= htmlspecialchars($rawName, ENT_QUOTES) ?>')">重命名</button>
                    <?php if ($editable): ?>
                    <button class="btn-sm btn-edit" onclick="openEdit('<?= htmlspecialchars($rawName, ENT_QUOTES) ?>')">编辑</button>
                    <?php endif; ?>
                    <?= deleteForm('/myweb/admin/files.php', 'delete', $rawName, '删除') ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- 重命名弹窗 -->
<div id="renameModal" class="modal-overlay" style="display:none" onclick="if(event.target===this)closeModal('renameModal')">
    <div class="modal-box">
        <div class="modal-header">
            <h4>重命名文件</h4>
            <button class="modal-close" onclick="closeModal('renameModal')">✕</button>
        </div>
        <form method="post" id="renameForm">
            <?= csrfField() ?>
            <input type="hidden" name="rename_old" id="renameOld">
            <div class="form-group">
                <label>新文件名</label>
                <input type="text" name="rename_new" id="renameNew" required>
                <small class="text-muted text-sm">扩展名会自动保持一致</small>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn" onclick="closeModal('renameModal')">取消</button>
                <button type="submit" class="btn btn-primary">确认重命名</button>
            </div>
        </form>
    </div>
</div>

<!-- 编辑文本弹窗 -->
<div id="editModal" class="modal-overlay" style="display:none" onclick="if(event.target===this)closeModal('editModal')">
    <div class="modal-box modal-box-lg">
        <div class="modal-header">
            <h4>编辑文件内容</h4>
            <button class="modal-close" onclick="closeModal('editModal')">✕</button>
        </div>
        <form method="post" id="editForm">
            <?= csrfField() ?>
            <input type="hidden" name="edit_file" id="editFile">
            <div class="form-group">
                <label id="editFileName" style="font-weight:600;color:var(--accent-light);margin-bottom:var(--sp-2);display:block"></label>
                <div class="code-editor-wrap">
                    <textarea name="edit_content" id="editContent" rows="24" class="code-editor-textarea" spellcheck="false"></textarea>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn" onclick="closeModal('editModal')">取消</button>
                <button type="submit" class="btn btn-primary">保存修改</button>
            </div>
        </form>
    </div>
</div>

<script>
function copyUrl(url) {
    navigator.clipboard.writeText(url).then(function() {
        var btn = event.target;
        var text = btn.textContent;
        btn.textContent = '✅ 已复制';
        setTimeout(function() { btn.textContent = text; }, 1500);
    });
}

function openRename(name) {
    document.getElementById('renameOld').value = name;
    // 预填：提取原始文件名部分（去掉随机前缀）
    var parts = name.split('_');
    var displayName = parts.length > 1 ? parts.slice(1).join('_') : name;
    document.getElementById('renameNew').value = displayName;
    document.getElementById('renameModal').style.display = 'flex';
    document.getElementById('renameNew').focus();
    document.getElementById('renameNew').select();
}

function openEdit(name) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/myweb/uploads/' + encodeURIComponent(name), true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            document.getElementById('editFile').value = name;
            document.getElementById('editFileName').textContent = name;
            document.getElementById('editContent').value = xhr.responseText;
            document.getElementById('editModal').style.display = 'flex';
        } else {
            alert('无法读取文件内容');
        }
    };
    xhr.onerror = function() { alert('读取失败'); };
    xhr.send();
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

// 拖拽上传
(function() {
    var zone = document.getElementById('uploadZone');
    if (!zone) return;
    zone.addEventListener('dragover', function(e) { e.preventDefault(); zone.style.borderColor = '#6c8cff'; zone.style.background = 'rgba(108,140,255,0.08)'; });
    zone.addEventListener('dragleave', function() { zone.style.borderColor = ''; zone.style.background = ''; });
    zone.addEventListener('drop', function(e) {
        e.preventDefault();
        zone.style.borderColor = ''; zone.style.background = '';
        var dt = new DataTransfer();
        for (var i = 0; i < e.dataTransfer.files.length; i++) dt.items.add(e.dataTransfer.files[i]);
        document.getElementById('fileInput').files = dt.files;
        document.getElementById('uploadForm').submit();
    });
})();
</script>

<?php require_once '../includes/footer.php'; ?>
