<?php
/**
 * Mockery - Version API
 * Fast endpoint for polling page versions
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$config = require __DIR__ . '/../config.php';
$pagesDir = $config['pages_dir'];

$pageName = preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['page'] ?? ''));

if (empty($pageName)) {
    echo json_encode(['success' => false, 'error' => 'Brak nazwy strony']);
    exit;
}

$pageFile = $pagesDir . '/' . $pageName . '.html';

if (!file_exists($pageFile)) {
    echo json_encode(['success' => false, 'error' => 'Strona nie istnieje']);
    exit;
}

// Return file modification time as version
clearstatcache(true, $pageFile);
$mtime = filemtime($pageFile);

echo json_encode([
    'success' => true,
    'version' => gmdate('D, d M Y H:i:s', $mtime) . ' GMT',
    'timestamp' => $mtime
]);
