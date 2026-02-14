<?php
/**
 * Mockery - Generate API
 * Calls Claude API to generate/modify HTML pages
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
$apiKey = $config['claude_api_key'];
$model = $config['claude_model'];

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
$pageName = preg_replace('/[^a-z0-9-]/', '', strtolower($input['page'] ?? ''));
$message = trim($input['message'] ?? '');
$history = $input['history'] ?? [];

if (empty($pageName) || empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Brak wymaganych parametrów']);
    exit;
}

$pageFile = $pagesDir . '/' . $pageName . '.html';

if (!file_exists($pageFile)) {
    echo json_encode(['success' => false, 'error' => 'Strona nie istnieje']);
    exit;
}

// Read current page content
$currentHtml = file_get_contents($pageFile);

// Build system prompt
$systemPrompt = <<<PROMPT
Jesteś ekspertem od tworzenia stron HTML w stylu Microsoft Fluent Design. Tworzysz kod HTML używając Tailwind CSS.

ZASADY STYLU FLUENT DESIGN:
1. Kolory:
   - Primary: #0078d4 (niebieski Microsoft)
   - Primary dark: #106ebe
   - Primary light: #deecf9
   - Backgrounds: #ffffff (primary), #faf9f8 (secondary), #f3f2f1 (tertiary)
   - Borders: #edebe9, #8a8886 (dark)
   - Text: #323130 (primary), #605e5c (secondary), #a19f9d (disabled)
   - Accent: #107c10 (success), #ffb900 (warning), #d13438 (error), #5c2d91 (purple)

2. Cienie (Fluent shadows):
   - fluent-2: 0 0 2px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.14)
   - fluent-4: 0 0 2px rgba(0,0,0,0.12), 0 2px 4px rgba(0,0,0,0.14)
   - fluent-8: 0 0 2px rgba(0,0,0,0.12), 0 4px 8px rgba(0,0,0,0.14)
   - fluent-16: 0 0 2px rgba(0,0,0,0.12), 0 8px 16px rgba(0,0,0,0.14)

3. Typografia:
   - Font: Segoe UI, Inter, system-ui, sans-serif
   - Używaj font-semibold dla nagłówków
   - Tekst powinien być czytelny i dobrze zhierarchizowany

4. Komponenty:
   - Przyciski: zaokrąglone (rounded), z hover states
   - Karty: białe tło, delikatne cienie, zaokrąglone rogi (rounded-lg)
   - Inputy: border-fluent-border, focus:border-fluent-primary
   - Używaj transition-colors dla płynnych animacji

5. Layout:
   - Używaj flex i grid
   - Odpowiednie paddingi i marginesy (gap-*, p-*, m-*)
   - Responsywny design (sm:, md:, lg:)

WAŻNE INSTRUKCJE:
- Zwracaj TYLKO kod HTML - bez markdown, bez ```html, bez wyjaśnień
- Kod musi być kompletną stroną HTML (<!DOCTYPE html> do </html>)
- Zachowaj istniejącą konfigurację Tailwind w <head>
- Zachowaj spójność z dotychczasowym stylem strony
- Kod musi działać samodzielnie w iframe
- Używaj polskich tekstów
- NIE używaj zewnętrznych obrazków - używaj SVG lub placeholderów z gradientami
- Dla ikon używaj inline SVG

OBECNA ZAWARTOŚĆ STRONY:
```html
{$currentHtml}
```

Zmodyfikuj stronę zgodnie z instrukcjami użytkownika, zachowując styl Fluent Design.
PROMPT;

// Build messages array with history
$messages = [];

// Add conversation history
foreach ($history as $msg) {
    if (isset($msg['role']) && isset($msg['content'])) {
        $role = $msg['role'] === 'assistant' ? 'assistant' : 'user';
        $messages[] = [
            'role' => $role,
            'content' => $msg['content']
        ];
    }
}

// Add current message
$messages[] = [
    'role' => 'user',
    'content' => $message
];

// Call Claude API
$response = callClaudeAPI($apiKey, $model, $systemPrompt, $messages);

if (!$response['success']) {
    echo json_encode($response);
    exit;
}

$generatedHtml = $response['content'];

// Clean up response - remove markdown code blocks if present
$generatedHtml = preg_replace('/^```html?\s*/i', '', $generatedHtml);
$generatedHtml = preg_replace('/\s*```\s*$/', '', $generatedHtml);
$generatedHtml = trim($generatedHtml);

// Validate HTML
if (strpos($generatedHtml, '<!DOCTYPE') === false && strpos($generatedHtml, '<html') === false) {
    echo json_encode([
        'success' => false,
        'error' => 'Wygenerowany kod nie jest prawidłowym HTML'
    ]);
    exit;
}

// Save the new HTML
if (file_put_contents($pageFile, $generatedHtml) === false) {
    echo json_encode([
        'success' => false,
        'error' => 'Nie udało się zapisać strony'
    ]);
    exit;
}

// Touch file to update modification time
touch($pageFile);

echo json_encode([
    'success' => true,
    'message' => 'Strona została zaktualizowana!',
    'version' => filemtime($pageFile)
]);

/**
 * Call Claude API
 */
function callClaudeAPI($apiKey, $model, $systemPrompt, $messages) {
    $url = 'https://api.anthropic.com/v1/messages';

    $data = [
        'model' => $model,
        'max_tokens' => 8192,
        'system' => $systemPrompt,
        'messages' => $messages
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01'
        ],
        CURLOPT_TIMEOUT => 120, // 2 minute timeout for generation
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return [
            'success' => false,
            'error' => 'Błąd połączenia: ' . $error
        ];
    }

    $result = json_decode($response, true);

    if ($httpCode !== 200) {
        $errorMsg = $result['error']['message'] ?? 'Nieznany błąd API';
        return [
            'success' => false,
            'error' => 'Błąd API: ' . $errorMsg
        ];
    }

    if (!isset($result['content'][0]['text'])) {
        return [
            'success' => false,
            'error' => 'Nieprawidłowa odpowiedź z API'
        ];
    }

    return [
        'success' => true,
        'content' => $result['content'][0]['text']
    ];
}
