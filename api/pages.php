<?php
/**
 * Mockery - Pages API
 * Handles page listing, creation, and deletion
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$config = require __DIR__ . '/../config.php';
$pagesDir = $config['pages_dir'];

// Ensure pages directory exists
if (!is_dir($pagesDir)) {
    mkdir($pagesDir, 0755, true);
}

/**
 * Get list of all pages
 */
function getPages($pagesDir) {
    $pages = [];
    $files = glob($pagesDir . '/*.html');

    foreach ($files as $file) {
        $name = basename($file, '.html');
        $pages[] = [
            'name' => $name,
            'modified' => filemtime($file),
            'size' => filesize($file)
        ];
    }

    // Sort by modification time (newest first)
    usort($pages, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });

    return $pages;
}

/**
 * Create a new page with default template
 */
function createPage($pagesDir, $name) {
    // Sanitize name
    $name = preg_replace('/[^a-z0-9-]/', '', strtolower($name));

    if (empty($name)) {
        return ['success' => false, 'error' => 'Nieprawidłowa nazwa strony'];
    }

    $filePath = $pagesDir . '/' . $name . '.html';

    if (file_exists($filePath)) {
        return ['success' => false, 'error' => 'Strona o tej nazwie już istnieje'];
    }

    // Default template with Fluent Design
    $template = <<<HTML
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$name} - Mockery</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'fluent': ['"Segoe UI"', 'Inter', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        fluent: {
                            primary: '#0078d4',
                            'primary-dark': '#106ebe',
                            'primary-light': '#deecf9',
                            'bg-primary': '#ffffff',
                            'bg-secondary': '#faf9f8',
                            'bg-tertiary': '#f3f2f1',
                            'border': '#edebe9',
                            'border-dark': '#8a8886',
                            'text-primary': '#323130',
                            'text-secondary': '#605e5c',
                            'text-disabled': '#a19f9d',
                            'success': '#107c10',
                            'warning': '#ffb900',
                            'error': '#d13438',
                            'purple': '#5c2d91',
                        }
                    },
                    boxShadow: {
                        'fluent-2': '0 0 2px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.14)',
                        'fluent-4': '0 0 2px rgba(0,0,0,0.12), 0 2px 4px rgba(0,0,0,0.14)',
                        'fluent-8': '0 0 2px rgba(0,0,0,0.12), 0 4px 8px rgba(0,0,0,0.14)',
                        'fluent-16': '0 0 2px rgba(0,0,0,0.12), 0 8px 16px rgba(0,0,0,0.14)',
                    }
                }
            }
        }
    </script>
    <style>
        * { font-family: 'Segoe UI', 'Inter', system-ui, sans-serif; }
    </style>
</head>
<body class="bg-fluent-bg-secondary min-h-screen font-fluent antialiased">
    <div class="max-w-4xl mx-auto p-8">
        <div class="bg-fluent-bg-primary rounded-lg shadow-fluent-8 p-8">
            <h1 class="text-3xl font-semibold text-fluent-text-primary mb-4">Witaj na stronie {$name}</h1>
            <p class="text-fluent-text-secondary mb-6">
                Ta strona została właśnie utworzona. Użyj czatu AI, aby opisać jak ma wyglądać.
            </p>
            <div class="flex gap-3">
                <button class="px-4 py-2 bg-fluent-primary hover:bg-fluent-primary-dark text-white font-medium rounded transition-colors">
                    Przykładowy przycisk
                </button>
                <button class="px-4 py-2 bg-fluent-bg-secondary hover:bg-fluent-bg-tertiary border border-fluent-border text-fluent-text-primary font-medium rounded transition-colors">
                    Przycisk drugorzędny
                </button>
            </div>
        </div>
    </div>
</body>
</html>
HTML;

    if (file_put_contents($filePath, $template) !== false) {
        return ['success' => true, 'name' => $name];
    }

    return ['success' => false, 'error' => 'Nie udało się utworzyć pliku'];
}

/**
 * Delete a page
 */
function deletePage($pagesDir, $name) {
    $name = preg_replace('/[^a-z0-9-]/', '', strtolower($name));
    $filePath = $pagesDir . '/' . $name . '.html';

    if (!file_exists($filePath)) {
        return ['success' => false, 'error' => 'Strona nie istnieje'];
    }

    if (unlink($filePath)) {
        return ['success' => true];
    }

    return ['success' => false, 'error' => 'Nie udało się usunąć strony'];
}

// Handle requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // List pages
    echo json_encode([
        'success' => true,
        'pages' => getPages($pagesDir)
    ]);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $name = $input['name'] ?? '';

    switch ($action) {
        case 'create':
            echo json_encode(createPage($pagesDir, $name));
            break;
        case 'delete':
            echo json_encode(deletePage($pagesDir, $name));
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Nieznana akcja']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
