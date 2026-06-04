<?php
// 自动检测开发/生产环境
define('DEBUG_MODE', in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']));

$configDir = __DIR__ . '/../../myweb-config';
$configFile = $configDir . '/database.php';
if (!file_exists($configFile)) {
    $configDir = __DIR__ . '/../../../myweb-config';
    $configFile = $configDir . '/database.php';
}
require_once $configFile;
require_once __DIR__ . '/helpers.php';
