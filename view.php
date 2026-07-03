<?php
$pageTitle = '文件查看';
require_once 'includes/header.php';
require_once __DIR__ . '/includes/file_service.php';

// 登录控制
if (!isLoggedIn()) {
    redirect('/myweb/login.php?redirect=' . urlencode('/myweb/view.php?file=' . urlencode($_GET['file'] ?? '')));
}

$fileName = basename($_GET['file'] ?? '');
$filePath = __DIR__ . '/uploads/' . $fileName;

if (!$fileName || !file_exists($filePath) || !is_file($filePath)) {
    redirect('/myweb/files.php');
}

$ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$fileSize = filesize($filePath);
$fileTime = filemtime($filePath);
$fileIcon = FileService::getFileIcon($ext, in_array($ext, ['jpg','jpeg','png','gif','webp','ico']));

$isImage  = in_array($ext, ['jpg','jpeg','png','gif','webp','ico']);
$isPdf    = $ext === 'pdf';
$isOffice = in_array($ext, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']);
$isText   = in_array($ext, ['txt', 'csv', 'log', 'md', 'xml', 'json', 'js', 'css', 'php']);
$isCode   = in_array($ext, ['php', 'js', 'css', 'html', 'htm', 'sql', 'json', 'xml', 'py', 'java', 'c', 'cpp', 'h', 'sh', 'bat']);
$isVideo  = in_array($ext, ['mp4', 'webm', 'mov', 'avi', 'mkv']);
$isAudio  = in_array($ext, ['mp3', 'wav', 'ogg', 'flac', 'aac']);

$fileUrl     = '/myweb/uploads/' . rawurlencode($fileName);
$officeLabel = match(true) {
    in_array($ext, ['doc','docx']) => 'Word',
    in_array($ext, ['xls','xlsx']) => 'Excel',
    in_array($ext, ['ppt','pptx']) => 'PowerPoint',
    default => 'Office',
};
?>

<div class="view-container">
    <div class="view-header">
        <a href="javascript:history.back()" class="back-btn">← 返回</a>
        <div class="file-info">
            <h1><?= htmlspecialchars($fileName) ?></h1>
            <div class="meta"><?= formatSize($fileSize) ?> · <?= date('Y-m-d H:i', $fileTime) ?></div>
        </div>
        <a href="<?= $fileUrl ?>" download="<?= htmlspecialchars($fileName) ?>" class="download-btn">⬇ 下载</a>
    </div>

    <?php if ($isImage): ?>
    <div class="image-viewer">
        <img src="<?= $fileUrl ?>" alt="<?= htmlspecialchars($fileName) ?>">
    </div>

    <?php elseif ($isPdf): ?>
    <div class="pdf-viewer">
        <iframe src="<?= $fileUrl ?>" title="PDF Viewer"></iframe>
    </div>

    <?php elseif ($isOffice): ?>
    <?php
    $officeContent = '';
    $officePages = 0;
    $isOoxml = in_array($ext, ['docx', 'xlsx', 'pptx']) && class_exists('ZipArchive');

    if ($isOoxml) {
        $zip = new ZipArchive();
        if ($zip->open($filePath) === true) {
            if ($ext === 'docx') {
                $xml = $zip->getFromName('word/document.xml');
                if ($xml) {
                    $doc = new DOMDocument();
                    @$doc->loadXML($xml);
                    $xp = new DOMXPath($doc);
                    $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                    $paragraphs = $xp->query('//w:p');
                    $officePages = $paragraphs->length;
                    foreach ($paragraphs as $p) {
                        $texts = $xp->query('.//w:t', $p);
                        $line = '';
                        foreach ($texts as $t) $line .= $t->textContent;
                        $pStyle = $xp->query('./w:pPr/w:pStyle/@w:val', $p);
                        $isHeading = $pStyle->length > 0 && strpos($pStyle->item(0)->textContent, 'Heading') !== false;
                        if (trim($line) !== '') {
                            $officeContent .= $isHeading
                                ? '<h3 class="doc-h">' . htmlspecialchars(trim($line)) . '</h3>'
                                : '<p class="doc-p">' . htmlspecialchars(trim($line)) . '</p>';
                        } else {
                            $officeContent .= '<p class="doc-p doc-empty">&nbsp;</p>';
                        }
                    }
                }
            } elseif ($ext === 'xlsx') {
                $ss = [];
                $ssXml = $zip->getFromName('xl/sharedStrings.xml');
                if ($ssXml) {
                    $ssDoc = new DOMDocument(); @$ssDoc->loadXML($ssXml);
                    $ssXp = new DOMXPath($ssDoc);
                    $ssXp->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                    foreach ($ssXp->query('//s:si') as $si) {
                        $text = '';
                        foreach ($ssXp->query('.//s:t', $si) as $t) $text .= $t->textContent;
                        $ss[] = $text;
                    }
                }
                $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
                if ($sheetXml) {
                    $shDoc = new DOMDocument(); @$shDoc->loadXML($sheetXml);
                    $shXp = new DOMXPath($shDoc);
                    $shXp->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                    $rows = $shXp->query('//s:sheetData/s:row');
                    $officePages = $rows->length;
                    $maxCol = 0; $allRows = [];
                    foreach ($rows as $row) {
                        $cells = [];
                        foreach ($shXp->query('./s:c', $row) as $cell) {
                            $ref = $cell->getAttribute('r');
                            $type = $cell->getAttribute('t');
                            $vNode = $shXp->query('./s:v', $cell)->item(0);
                            $val = $vNode ? $vNode->textContent : '';
                            if ($type === 's' && $val !== '' && isset($ss[(int)$val])) $val = $ss[(int)$val];
                            preg_match('/^([A-Z]+)/', $ref, $m);
                            $cn = 0;
                            for ($i = 0; $i < strlen($m[1] ?? ''); $i++) $cn = $cn * 26 + (ord($m[1][$i]) - 64);
                            if ($cn > $maxCol) $maxCol = $cn;
                            $cells[$cn] = $val;
                        }
                        $allRows[] = $cells;
                    }
                    if ($allRows) {
                        $officeContent = '<div class="xls-wrap"><table class="xls-table"><thead><tr>';
                        for ($c = 1; $c <= $maxCol; $c++) $officeContent .= '<th>' . htmlspecialchars($allRows[0][$c] ?? '') . '</th>';
                        $officeContent .= '</tr></thead><tbody>';
                        for ($r = 1; $r < count($allRows); $r++) {
                            $officeContent .= '<tr>';
                            for ($c = 1; $c <= $maxCol; $c++) $officeContent .= '<td>' . htmlspecialchars($allRows[$r][$c] ?? '') . '</td>';
                            $officeContent .= '</tr>';
                        }
                        $officeContent .= '</tbody></table></div>';
                    }
                }
            } elseif ($ext === 'pptx') {
                for ($i = 1; $i <= 99; $i++) {
                    $sXml = $zip->getFromName("ppt/slides/slide{$i}.xml");
                    if (!$sXml) break;
                    $officePages++;
                    $sDoc = new DOMDocument(); @$sDoc->loadXML($sXml);
                    $sXp = new DOMXPath($sDoc);
                    $sXp->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
                    $lines = [];
                    foreach ($sXp->query('//a:t') as $t) {
                        $txt = trim($t->textContent);
                        if ($txt !== '') $lines[] = $txt;
                    }
                    $officeContent .= '<div class="ppt-slide"><div class="ppt-hdr">📄 第 ' . $i . ' 页</div>';
                    if ($lines) {
                        $officeContent .= '<div class="ppt-body">';
                        foreach ($lines as $l) $officeContent .= '<p class="ppt-txt">' . htmlspecialchars($l) . '</p>';
                        $officeContent .= '</div>';
                    } else {
                        $officeContent .= '<div class="ppt-body ppt-empty">（此页无文字内容）</div>';
                    }
                    $officeContent .= '</div>';
                }
            }
            $zip->close();
        }
    }

    $hasContent = trim(strip_tags($officeContent)) !== '';
    if (!$hasContent) {
        $officeContent = '<div class="view-fallback"><div class="big-icon">📋</div><h2>' . htmlspecialchars($fileName) . '</h2><p>无法解析此 ' . $officeLabel . ' 文件，请下载后使用 Office 软件查看</p><a href="' . $fileUrl . '" download="' . htmlspecialchars($fileName) . '" class="btn btn-primary">⬇ 下载文件</a></div>';
    }
    ?>
    <div class="office-wrap">
        <div class="office-bar">
            <span class="office-badge">📋 <?= $officeLabel ?></span>
            <span class="office-stat"><?= number_format(mb_strlen(strip_tags($officeContent))) ?> 字符</span>
            <?php if ($officePages > 0): ?>
            <span class="office-stat"><?= $officePages ?> <?= $ext === 'xlsx' ? '行' : '段' ?></span>
            <?php endif; ?>
            <span class="office-credit">本地解析</span>
            <a href="<?= $fileUrl ?>" download="<?= htmlspecialchars($fileName) ?>" class="btn-sm btn-download">⬇ 下载</a>
        </div>
        <div class="office-body"><?= $officeContent ?></div>
    </div>

    <?php elseif ($isVideo): ?>
    <div class="media-viewer">
        <video controls>
            <?php $videoMime = $ext === 'mov' ? 'quicktime' : ($ext === 'mkv' ? 'x-matroska' : $ext); ?>
            <source src="<?= $fileUrl ?>" type="video/<?= $videoMime ?>">
            您的浏览器不支持视频播放
        </video>
    </div>

    <?php elseif ($isAudio): ?>
    <div class="media-viewer">
        <audio controls>
            <source src="<?= $fileUrl ?>" type="audio/<?= $ext === 'mp3' ? 'mpeg' : $ext ?>">
            您的浏览器不支持音频播放
        </audio>
    </div>

    <?php elseif ($isText || $isCode): ?>
    <?php
        $content = FileService::readTextPreview($filePath);
        $langMap = ['php'=>'PHP','js'=>'JavaScript','css'=>'CSS','html'=>'HTML','sql'=>'SQL','json'=>'JSON','xml'=>'XML','py'=>'Python','java'=>'Java','md'=>'Markdown','csv'=>'CSV','log'=>'Log','txt'=>'Text','yml'=>'YAML','sh'=>'Shell'];
        $lang = $langMap[$ext] ?? 'Text';
        $lines = explode("\n", $content);
        $lineCount = count($lines);
        $lineNumWidth = max(3, strlen((string)$lineCount) + 1);
    ?>
    <div class="text-viewer">
        <div class="text-toolbar">
            <span class="lang-badge"><?= $lang ?></span>
            <span><?= number_format(mb_strlen($content)) ?> 字符</span>
            <span><?= $lineCount ?> 行</span>
            <span class="text-toolbar-right">
                <button class="btn-sm btn-copy-text" onclick="copyTextContent()" title="复制全部内容">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    复制
                </button>
            </span>
        </div>
        <div class="code-view">
            <table class="code-table">
                <?php foreach ($lines as $i => $line): ?>
                <tr>
                    <td class="code-line-num" data-line="<?= $i + 1 ?>"><?= $i + 1 ?></td>
                    <td class="code-line-content"><?= $line === '' ? ' ' : htmlspecialchars($line) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <?php else: ?>
    <div class="info-card">
        <div class="big-icon"><?= $fileIcon ?></div>
        <h2><?= htmlspecialchars($fileName) ?></h2>
        <p>此文件类型暂不支持在线预览</p>
        <div class="detail-row">
            <span>📦 <?= formatSize($fileSize) ?></span>
            <span>📁 .<?= strtoupper($ext) ?></span>
            <span>🕒 <?= date('Y-m-d', $fileTime) ?></span>
        </div>
        <a href="<?= $fileUrl ?>" download="<?= htmlspecialchars($fileName) ?>" class="btn btn-primary">⬇ 下载文件</a>
    </div>
    <?php endif; ?>
</div>

<script>
function copyTextContent() {
    var lines = document.querySelectorAll('.code-line-content');
    var text = [];
    lines.forEach(function(el) { text.push(el.textContent); });
    navigator.clipboard.writeText(text.join('\n')).then(function() {
        var btn = document.querySelector('.btn-copy-text');
        var orig = btn.innerHTML;
        btn.innerHTML = '✅ 已复制';
        setTimeout(function() { btn.innerHTML = orig; }, 2000);
    });
}
</script>
<?php require_once 'includes/footer.php'; ?>
