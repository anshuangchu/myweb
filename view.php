<?php
$pageTitle = '文件查看';
require_once 'includes/header.php';

$fileName = basename($_GET['file'] ?? '');
$filePath = __DIR__ . '/uploads/' . $fileName;

if (!$fileName || !file_exists($filePath)) {
    redirect('/myweb/files.php');
}

$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$fileSize = filesize($filePath);
$fileTime = filemtime($filePath);

$isImage = in_array($ext, ['jpg','jpeg','png','gif','webp','ico']);
$isPdf = $ext === 'pdf';
$isOffice = in_array($ext, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']);
$isText = in_array($ext, ['txt', 'csv', 'log', 'md', 'xml', 'json', 'js', 'css', 'php']);
$isVideo = in_array($ext, ['mp4', 'webm', 'mov', 'avi', 'mkv']);
$isAudio = in_array($ext, ['mp3', 'wav', 'ogg', 'flac', 'aac']);
$isCode = in_array($ext, ['php', 'js', 'css', 'html', 'htm', 'sql', 'json', 'xml', 'py', 'java', 'c', 'cpp', 'h', 'sh', 'bat']);

$fileUrl = '/myweb/uploads/' . rawurlencode($fileName);
$officeLabel = in_array($ext, ['doc','docx']) ? 'Word' : (in_array($ext, ['xls','xlsx']) ? 'Excel' : 'PowerPoint');
?>
<style>
.view-container { max-width: 960px; margin: 0 auto; padding: 24px 0; }
.view-header {
    display: flex; align-items: center; gap: 16px;
    padding: 16px 20px; margin-bottom: 20px;
    background: var(--bg-card); border: 1px solid var(--border);
    border-radius: var(--radius-lg);
}
.view-header .back-btn {
    padding: 6px 14px; border-radius: 6px; font-size: 0.85rem;
    border: 1px solid var(--border); color: var(--text-secondary);
    text-decoration: none; transition: all .2s; flex-shrink: 0;
}
.view-header .back-btn:hover { border-color: var(--accent); color: var(--text-primary); }
.view-header .file-info { flex: 1; min-width: 0; }
.view-header .file-info h1 {
    font-size: 1.1rem; font-weight: 600; white-space: nowrap;
    overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px;
}
.view-header .file-info .meta { font-size: 0.8rem; color: var(--text-muted); }
.view-header .download-btn {
    padding: 8px 20px; border-radius: 8px; font-size: 0.88rem;
    background: var(--accent); color: #fff; text-decoration: none;
    transition: opacity .2s; flex-shrink: 0;
}
.view-header .download-btn:hover { opacity: 0.85; }

/* Image viewer */
.image-viewer {
    display: flex; justify-content: center; align-items: center;
    min-height: 60vh; background: var(--bg-card);
    border: 1px solid var(--border); border-radius: var(--radius-lg);
    padding: 20px;
}
.image-viewer img {
    max-width: 100%; max-height: 75vh; object-fit: contain;
    border-radius: 8px; box-shadow: 0 4px 24px rgba(0,0,0,0.3);
}

/* PDF viewer */
.pdf-viewer { width: 100%; height: 80vh; border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; }
.pdf-viewer iframe { width: 100%; height: 100%; border: none; }

/* Office 文档预览 */
.office-wrap {
    background: var(--bg-card); border: 1px solid var(--border);
    border-radius: var(--radius-lg); overflow: hidden;
}
.office-bar {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 16px; border-bottom: 1px solid var(--border);
    background: var(--bg-hover); font-size: 0.82rem; color: var(--text-muted);
}
.office-badge {
    padding: 2px 10px; border-radius: 4px;
    background: rgba(88,166,255,0.12); color: var(--accent); font-weight: 500; font-size: 0.82rem;
}
.office-stat { color: var(--text-muted); font-size: 0.82rem; }
.office-body {
    padding: 24px 28px; max-height: 72vh; overflow-y: auto;
    font-size: 0.95rem; line-height: 1.8; color: var(--text-primary);
}
/* Word 文档样式 */
.doc-h {
    font-size: 1.15rem; font-weight: 700; margin: 20px 0 10px;
    padding-bottom: 6px; border-bottom: 1px solid var(--border);
    color: var(--accent);
}
.doc-p { margin: 6px 0; text-indent: 0; }
.doc-empty { height: 0.8em; }
.doc-meta {
    font-size: 0.8rem; color: var(--text-muted); padding: 8px 12px;
    margin: 12px 0; background: var(--bg-hover); border-radius: 6px;
}
.doc-meta-tag {
    display: inline-block; padding: 1px 8px; border-radius: 3px;
    background: rgba(88,166,255,0.1); color: var(--accent);
    font-size: 0.75rem; margin-right: 8px; font-weight: 500;
}
/* Excel 表格 */
.xls-wrap { overflow-x: auto; }
.xls-table {
    width: 100%; border-collapse: collapse; font-size: 0.85rem;
    font-family: 'Consolas', 'Monaco', monospace;
}
.xls-table th {
    background: var(--bg-hover); padding: 8px 12px; text-align: left;
    font-weight: 600; color: var(--text-primary);
    border: 1px solid var(--border); white-space: nowrap;
}
.xls-table td {
    padding: 6px 12px; border: 1px solid var(--border);
    color: var(--text-primary); white-space: nowrap;
}
.xls-table tbody tr:nth-child(even) td { background: rgba(128,128,128,0.03); }
.xls-table tbody tr:hover td { background: rgba(88,166,255,0.06); }
/* PPT 幻灯片 */
.ppt-slide {
    border: 1px solid var(--border); border-radius: 8px; margin-bottom: 16px;
    overflow: hidden; background: var(--bg-card);
}
.ppt-hdr {
    padding: 8px 16px; font-size: 0.82rem; font-weight: 600;
    background: var(--bg-hover); color: var(--text-muted);
    border-bottom: 1px solid var(--border);
}
.ppt-body { padding: 14px 20px; }
.ppt-txt { margin: 4px 0; font-size: 0.92rem; }
.ppt-empty { color: var(--text-muted); font-style: italic; font-size: 0.85rem; }

/* Text viewer */
.text-viewer {
    background: var(--bg-card); border: 1px solid var(--border);
    border-radius: var(--radius-lg); overflow: hidden;
}
.text-viewer .text-toolbar {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 16px; border-bottom: 1px solid var(--border);
    background: var(--bg-hover); font-size: 0.82rem; color: var(--text-muted);
}
.text-viewer .text-toolbar .lang-badge {
    padding: 2px 10px; border-radius: 4px;
    background: rgba(88,166,255,0.12); color: var(--accent); font-weight: 500;
}
.text-viewer pre {
    margin: 0; padding: 20px 24px; overflow-x: auto;
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 0.85rem; line-height: 1.6; color: var(--text-primary);
    white-space: pre-wrap; word-wrap: break-word;
}

/* Video/Audio player */
.media-viewer {
    background: var(--bg-card); border: 1px solid var(--border);
    border-radius: var(--radius-lg); overflow: hidden; padding: 20px;
}
.media-viewer video, .media-viewer audio { width: 100%; border-radius: 8px; }
.media-viewer audio { margin: 40px 0; }

/* File info card */
.info-card {
    background: var(--bg-card); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 40px; text-align: center;
}
.info-card .big-icon { font-size: 4rem; margin-bottom: 16px; }
.info-card h2 { font-size: 1.2rem; font-weight: 600; margin-bottom: 8px; color: var(--text-primary); }
.info-card .detail-row {
    display: flex; justify-content: center; gap: 32px; margin-top: 16px;
    font-size: 0.88rem; color: var(--text-muted);
}
.info-card .detail-row span { display: flex; align-items: center; gap: 6px; }
</style>

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
                // 文档正文
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
                        foreach ($texts as $t) {
                            $line .= $t->textContent;
                        }
                        $pStyle = $xp->query('./w:pPr/w:pStyle/@w:val', $p);
                        $isHeading = $pStyle->length > 0 && strpos($pStyle->item(0)->textContent, 'Heading') !== false;

                        if (trim($line) !== '') {
                            if ($isHeading) {
                                $officeContent .= '<h3 class="doc-h">' . htmlspecialchars(trim($line)) . '</h3>';
                            } else {
                                $officeContent .= '<p class="doc-p">' . htmlspecialchars(trim($line)) . '</p>';
                            }
                        } else {
                            $officeContent .= '<p class="doc-p doc-empty">&nbsp;</p>';
                        }
                    }
                }
                // 页眉页脚
                foreach (['word/header1.xml' => '页眉', 'word/footer1.xml' => '页脚'] as $extra => $label) {
                    $exml = $zip->getFromName($extra);
                    if ($exml) {
                        $edoc = new DOMDocument();
                        @$edoc->loadXML($exml);
                        $exp = new DOMXPath($edoc);
                        $exp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                        $lines = [];
                        foreach ($exp->query('//w:t') as $t) {
                            $txt = trim($t->textContent);
                            if ($txt !== '') $lines[] = $txt;
                        }
                        if ($lines) {
                            $officeContent .= '<div class="doc-meta"><span class="doc-meta-tag">' . $label . '</span>' . htmlspecialchars(implode(' | ', $lines)) . '</div>';
                        }
                    }
                }
            } elseif ($ext === 'xlsx') {
                // 共享字符串表
                $ss = [];
                $ssXml = $zip->getFromName('xl/sharedStrings.xml');
                if ($ssXml) {
                    $ssDoc = new DOMDocument();
                    @$ssDoc->loadXML($ssXml);
                    $ssXp = new DOMXPath($ssDoc);
                    $ssXp->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                    foreach ($ssXp->query('//s:si') as $si) {
                        $text = '';
                        foreach ($ssXp->query('.//s:t', $si) as $t) {
                            $text .= $t->textContent;
                        }
                        $ss[] = $text;
                    }
                }
                // 第一个工作表
                $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
                if ($sheetXml) {
                    $shDoc = new DOMDocument();
                    @$shDoc->loadXML($sheetXml);
                    $shXp = new DOMXPath($shDoc);
                    $shXp->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                    $rows = $shXp->query('//s:sheetData/s:row');
                    $officePages = $rows->length;
                    $maxCol = 0;
                    $allRows = [];

                    foreach ($rows as $row) {
                        $cells = [];
                        foreach ($shXp->query('./s:c', $row) as $cell) {
                            $ref = $cell->getAttribute('r');
                            $type = $cell->getAttribute('t');
                            $vNode = $shXp->query('./s:v', $cell)->item(0);
                            $val = $vNode ? $vNode->textContent : '';
                            if ($type === 's' && $val !== '' && isset($ss[(int)$val])) {
                                $val = $ss[(int)$val];
                            }
                            preg_match('/^([A-Z]+)/', $ref, $m);
                            $col = $m[1] ?? '';
                            $cn = 0;
                            for ($i = 0; $i < strlen($col); $i++) {
                                $cn = $cn * 26 + (ord($col[$i]) - 64);
                            }
                            if ($cn > $maxCol) $maxCol = $cn;
                            $cells[$cn] = $val;
                        }
                        $allRows[] = $cells;
                    }

                    if ($allRows) {
                        $officeContent = '<div class="xls-wrap"><table class="xls-table"><tbody>';
                        foreach ($allRows as $idx => $cells) {
                            $tag = $idx === 0 ? 'thead' : 'tbody';
                            if ($idx === 0) {
                                $officeContent .= '<thead><tr>';
                                for ($c = 1; $c <= $maxCol; $c++) {
                                    $officeContent .= '<th>' . htmlspecialchars($cells[$c] ?? '') . '</th>';
                                }
                                $officeContent .= '</tr></thead><tbody>';
                            } else {
                                $officeContent .= '<tr>';
                                for ($c = 1; $c <= $maxCol; $c++) {
                                    $officeContent .= '<td>' . htmlspecialchars($cells[$c] ?? '') . '</td>';
                                }
                                $officeContent .= '</tr>';
                            }
                        }
                        $officeContent .= '</tbody></table></div>';
                    }
                }
            } elseif ($ext === 'pptx') {
                for ($i = 1; $i <= 99; $i++) {
                    $sXml = $zip->getFromName("ppt/slides/slide{$i}.xml");
                    if (!$sXml) break;
                    $officePages++;

                    $sDoc = new DOMDocument();
                    @$sDoc->loadXML($sXml);
                    $sXp = new DOMXPath($sDoc);
                    $sXp->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');

                    $lines = [];
                    foreach ($sXp->query('//a:t') as $t) {
                        $txt = trim($t->textContent);
                        if ($txt !== '') $lines[] = $txt;
                    }

                    $officeContent .= '<div class="ppt-slide">';
                    $officeContent .= '<div class="ppt-hdr">📄 第 ' . $i . ' 页</div>';
                    if ($lines) {
                        $officeContent .= '<div class="ppt-body">';
                        foreach ($lines as $l) {
                            $officeContent .= '<p class="ppt-txt">' . htmlspecialchars($l) . '</p>';
                        }
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
        $officeContent = '<div style="text-align:center;padding:60px 20px"><div style="font-size:4rem;margin-bottom:16px">📋</div><h2 style="font-size:1.1rem;font-weight:600;margin-bottom:8px">' . htmlspecialchars($fileName) . '</h2><p style="color:var(--text-secondary)">无法解析此 ' . $officeLabel . ' 文件，请下载后使用 Office 软件查看</p><a href="' . $fileUrl . '" download="' . htmlspecialchars($fileName) . '" class="btn" style="display:inline-flex;align-items:center;gap:6px;margin-top:16px;background:var(--accent);color:#fff;border-color:var(--accent)">⬇ 下载文件</a></div>';
    }
    ?>
    <div class="office-wrap">
        <div class="office-bar">
            <span class="office-badge">📋 <?= $officeLabel ?></span>
            <span class="office-stat"><?= number_format(mb_strlen(strip_tags($officeContent))) ?> 字符</span>
            <?php if ($officePages > 0 && $ext !== 'xlsx'): ?>
            <span class="office-stat"><?= $officePages ?> 段</span>
            <?php elseif ($officePages > 0 && $ext === 'xlsx'): ?>
            <span class="office-stat"><?= $officePages ?> 行</span>
            <?php endif; ?>
            <span style="margin-left:auto;color:var(--text-muted);font-size:0.8rem">本地解析</span>
            <a href="<?= $fileUrl ?>" download="<?= htmlspecialchars($fileName) ?>" class="btn-sm" style="background:var(--accent);color:#fff;border-color:var(--accent);text-decoration:none;flex-shrink:0">⬇ 下载</a>
        </div>
        <div class="office-body"><?= $officeContent ?></div>
    </div>

    <?php elseif ($isVideo): ?>
    <div class="media-viewer">
        <video controls autoplay>
            <?php $videoMime = $ext === 'mov' ? 'quicktime' : ($ext === 'mkv' ? 'x-matroska' : $ext); ?>
            <source src="<?= $fileUrl ?>" type="video/<?= $videoMime ?>">
            您的浏览器不支持视频播放
        </video>
    </div>

    <?php elseif ($isAudio): ?>
    <div class="media-viewer">
        <audio controls autoplay>
            <source src="<?= $fileUrl ?>" type="audio/<?= $ext === 'mp3' ? 'mpeg' : $ext ?>">
            您的浏览器不支持音频播放
        </audio>
    </div>

    <?php elseif ($isText || $isCode): ?>
    <?php
        $content = file_get_contents($filePath);
        $langMap = ['php'=>'PHP','js'=>'JavaScript','css'=>'CSS','html'=>'HTML','htm'=>'HTML','sql'=>'SQL','json'=>'JSON','xml'=>'XML','py'=>'Python','java'=>'Java','c'=>'C','cpp'=>'C++','h'=>'C/C++','sh'=>'Shell','bat'=>'Batch','md'=>'Markdown','csv'=>'CSV','log'=>'Log','txt'=>'Text'];
        $lang = $langMap[$ext] ?? 'Text';
        if (mb_strlen($content) > 50000) $content = mb_substr($content, 0, 50000) . "\n\n... (文件过大，仅显示前 50000 字符)";
    ?>
    <div class="text-viewer">
        <div class="text-toolbar">
            <span class="lang-badge"><?= $lang ?></span>
            <span><?= number_format(mb_strlen($content)) ?> 字符</span>
            <span style="margin-left:auto">文件编码: UTF-8</span>
        </div>
        <pre><?= htmlspecialchars($content) ?></pre>
    </div>

    <?php else: ?>
    <div class="info-card">
        <div class="big-icon">📄</div>
        <h2><?= htmlspecialchars($fileName) ?></h2>
        <p style="color:var(--text-secondary);margin-bottom:8px">此文件类型暂不支持在线预览</p>
        <div class="detail-row">
            <span>📦 <?= formatSize($fileSize) ?></span>
            <span>📁 .<?= strtoupper($ext) ?></span>
            <span>🕒 <?= date('Y-m-d', $fileTime) ?></span>
        </div>
        <a href="<?= $fileUrl ?>" download="<?= htmlspecialchars($fileName) ?>" class="btn" style="display:inline-flex;align-items:center;gap:6px;margin-top:20px;background:var(--accent);color:#fff;border-color:var(--accent)">⬇ 下载文件</a>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
