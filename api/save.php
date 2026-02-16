<?php
/**
 * Mockery - Save API
 * Saves patched HTML back to file
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$config = require __DIR__ . '/../config.php';
$pagesDir = $config['pages_dir'];

$input = json_decode(file_get_contents('php://input'), true);
$pageName = preg_replace('/[^a-z0-9-]/', '', strtolower($input['page'] ?? ''));
$html = $input['html'] ?? '';

if (empty($pageName)) {
    echo json_encode(['success' => false, 'error' => 'Brak nazwy strony']);
    exit;
}

if (empty($html)) {
    echo json_encode(['success' => false, 'error' => 'Brak zawartości HTML']);
    exit;
}

$pageFile = $pagesDir . '/' . $pageName . '.html';

if (!file_exists($pageFile)) {
    echo json_encode(['success' => false, 'error' => 'Strona nie istnieje']);
    exit;
}

// Clean up the HTML a bit
$html = trim($html);

// Ensure it starts with DOCTYPE
if (stripos($html, '<!DOCTYPE') !== 0) {
    $html = "<!DOCTYPE html>\n" . $html;
}

// Save the file
if (file_put_contents($pageFile, $html) !== false) {
    touch($pageFile);
    clearstatcache(true, $pageFile);

    echo json_encode([
        'success' => true,
        'version' => filemtime($pageFile)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Nie udało się zapisać pliku'
    ]);
}
