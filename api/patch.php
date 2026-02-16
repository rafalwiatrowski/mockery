<?php
/**
 * Mockery - Patch API (Fast incremental updates)
 * Uses streaming + JSON patches for instant changes
 */

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('X-Accel-Buffering: no'); // Disable nginx buffering

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendSSE('error', ['error' => 'Method not allowed']);
    exit;
}

// Flush output immediately
ob_implicit_flush(true);
if (ob_get_level()) ob_end_flush();

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
    sendSSE('error', ['error' => 'Brak wymaganych parametrów']);
    exit;
}

$pageFile = $pagesDir . '/' . $pageName . '.html';

if (!file_exists($pageFile)) {
    sendSSE('error', ['error' => 'Strona nie istnieje']);
    exit;
}

$currentHtml = file_get_contents($pageFile);

// Determine if this is a simple change (patch) or complex (full regen)
$isSimpleChange = isSimpleChange($message);

if ($isSimpleChange) {
    // PATCH MODE - fast incremental updates
    handlePatchMode($apiKey, $model, $currentHtml, $message, $history, $pageFile);
} else {
    // FULL MODE - complete regeneration with streaming
    handleFullMode($apiKey, $model, $currentHtml, $message, $history, $pageFile);
}

/**
 * Determine if change is simple enough for patch mode
 */
function isSimpleChange($message) {
    $simplePatterns = [
        '/zmień.*tekst/i',
        '/zmień.*kolor/i',
        '/zmień.*tytuł/i',
        '/zmień.*napis/i',
        '/dodaj.*przycisk/i',
        '/dodaj.*tekst/i',
        '/dodaj.*link/i',
        '/usuń.*przycisk/i',
        '/usuń.*tekst/i',
        '/usuń.*sekcj/i',
        '/popraw/i',
        '/zamień/i',
        '/edytuj/i',
        '/ukryj/i',
        '/pokaż/i',
        '/przenieś/i',
        '/większ/i',
        '/mniejsz/i',
        '/pogrub/i',
        '/kursyw/i',
    ];

    foreach ($simplePatterns as $pattern) {
        if (preg_match($pattern, $message)) {
            return true;
        }
    }

    // Short messages are usually simple changes
    return strlen($message) < 100;
}

/**
 * Handle patch mode - fast JSON-based updates
 */
function handlePatchMode($apiKey, $model, $currentHtml, $message, $history, $pageFile) {
    sendSSE('status', ['status' => 'analyzing', 'message' => 'Analizuję zmianę...']);

    $systemPrompt = <<<PROMPT
Jesteś ekspertem od modyfikacji HTML. Analizujesz stronę i zwracasz TYLKO instrukcje zmian w formacie JSON.

OBECNA STRONA HTML:
```html
{$currentHtml}
```

INSTRUKCJE:
1. Przeanalizuj żądaną zmianę
2. Zwróć TYLKO JSON (bez markdown, bez ```json)
3. Format odpowiedzi:

{
  "changes": [
    {
      "action": "replace|insert|remove|setAttribute|addClass|removeClass|setStyle",
      "selector": "CSS selector do elementu",
      "content": "nowa zawartość HTML (dla replace/insert)",
      "attribute": "nazwa atrybutu (dla setAttribute)",
      "value": "wartość (dla setAttribute/setStyle)",
      "class": "nazwa klasy (dla addClass/removeClass)",
      "position": "before|after|prepend|append (dla insert)"
    }
  ],
  "summary": "Krótki opis co zostało zmienione"
}

DOSTĘPNE AKCJE:
- replace: Zamień innerHTML elementu
- insert: Wstaw nowy element (position: before/after/prepend/append)
- remove: Usuń element
- setAttribute: Ustaw atrybut (np. src, href, class)
- addClass: Dodaj klasę CSS
- removeClass: Usuń klasę CSS
- setStyle: Ustaw styl inline

PRZYKŁAD dla "zmień tytuł na Hello World":
{"changes":[{"action":"replace","selector":"h1","content":"Hello World"}],"summary":"Zmieniono tytuł na Hello World"}

WAŻNE:
- Używaj precyzyjnych selektorów CSS
- Dla wielu podobnych elementów używaj :nth-child() lub unikaj klasy
- Zachowuj styl Fluent Design (kolory: #0078d4, #323130, #faf9f8 itd.)
- Zwracaj CZYSTY JSON - bez żadnych dodatkowych znaków!
PROMPT;

    $messages = buildMessages($history, $message);

    // Use streaming for faster response
    $response = callClaudeStreaming($apiKey, $model, $systemPrompt, $messages, function($chunk) {
        // Stream progress
        static $buffer = '';
        $buffer .= $chunk;
        sendSSE('progress', ['chunk' => $chunk]);
    });

    if (!$response['success']) {
        sendSSE('error', ['error' => $response['error']]);
        return;
    }

    $jsonStr = cleanJsonResponse($response['content']);

    $patches = json_decode($jsonStr, true);

    if (!$patches || !isset($patches['changes'])) {
        // Fallback to full mode if JSON parsing fails
        sendSSE('status', ['status' => 'fallback', 'message' => 'Przełączam na pełną regenerację...']);
        handleFullMode($GLOBALS['config']['claude_api_key'], $GLOBALS['config']['claude_model'],
                       $GLOBALS['currentHtml'], $message, $history, $pageFile);
        return;
    }

    // Apply patches to HTML and save
    $newHtml = applyPatches($currentHtml, $patches['changes']);

    if ($newHtml) {
        file_put_contents($pageFile, $newHtml);
        touch($pageFile);
    }

    sendSSE('patches', [
        'patches' => $patches['changes'],
        'summary' => $patches['summary'] ?? 'Zmiany wprowadzone',
        'version' => filemtime($pageFile)
    ]);

    sendSSE('done', ['success' => true]);
}

/**
 * Handle full regeneration mode with streaming
 */
function handleFullMode($apiKey, $model, $currentHtml, $message, $history, $pageFile) {
    sendSSE('status', ['status' => 'generating', 'message' => 'Generuję stronę...']);

    $systemPrompt = <<<PROMPT
Jesteś ekspertem od tworzenia stron HTML w stylu Microsoft Fluent Design. Tworzysz kod HTML używając Tailwind CSS.

ZASADY STYLU FLUENT DESIGN:
1. Kolory: Primary #0078d4, Backgrounds #ffffff/#faf9f8/#f3f2f1, Text #323130/#605e5c
2. Cienie: fluent-4: 0 0 2px rgba(0,0,0,0.12), 0 2px 4px rgba(0,0,0,0.14)
3. Font: Segoe UI, Inter, system-ui
4. Komponenty: zaokrąglone, z hover states, transition-colors

OBECNA STRONA:
```html
{$currentHtml}
```

INSTRUKCJE:
- Zwracaj TYLKO kod HTML (bez markdown, bez ```html)
- Kompletna strona od <!DOCTYPE html> do </html>
- Zachowaj konfigurację Tailwind z <head>
- Dla ikon używaj inline SVG
PROMPT;

    $messages = buildMessages($history, $message);

    $fullContent = '';
    $response = callClaudeStreaming($apiKey, $model, $systemPrompt, $messages, function($chunk) use (&$fullContent) {
        $fullContent .= $chunk;
        sendSSE('progress', ['chunk' => $chunk, 'length' => strlen($fullContent)]);
    });

    if (!$response['success']) {
        sendSSE('error', ['error' => $response['error']]);
        return;
    }

    $generatedHtml = cleanHtmlResponse($response['content']);

    if (strpos($generatedHtml, '<!DOCTYPE') === false && strpos($generatedHtml, '<html') === false) {
        sendSSE('error', ['error' => 'Nieprawidłowy HTML']);
        return;
    }

    file_put_contents($pageFile, $generatedHtml);
    touch($pageFile);

    sendSSE('full', [
        'html' => $generatedHtml,
        'version' => filemtime($pageFile)
    ]);

    sendSSE('done', ['success' => true]);
}

/**
 * Build messages array
 */
function buildMessages($history, $message) {
    $messages = [];
    foreach ($history as $msg) {
        if (isset($msg['role']) && isset($msg['content'])) {
            $messages[] = [
                'role' => $msg['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $msg['content']
            ];
        }
    }
    $messages[] = ['role' => 'user', 'content' => $message];
    return $messages;
}

/**
 * Apply patches to HTML using DOMDocument
 */
function applyPatches($html, $patches) {
    // For server-side patching, we'll use simple string replacements
    // The main patching happens client-side via JavaScript
    return $html;
}

/**
 * Clean JSON response
 */
function cleanJsonResponse($content) {
    $content = preg_replace('/^```json?\s*/i', '', $content);
    $content = preg_replace('/\s*```\s*$/', '', $content);
    $content = trim($content);

    // Find JSON object
    if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
        return $matches[0];
    }
    return $content;
}

/**
 * Clean HTML response
 */
function cleanHtmlResponse($content) {
    $content = preg_replace('/^```html?\s*/i', '', $content);
    $content = preg_replace('/\s*```\s*$/', '', $content);
    return trim($content);
}

/**
 * Send SSE event
 */
function sendSSE($event, $data) {
    echo "event: {$event}\n";
    echo "data: " . json_encode($data) . "\n\n";
    flush();
}

/**
 * Call Claude API with streaming
 */
function callClaudeStreaming($apiKey, $model, $systemPrompt, $messages, $onChunk) {
    $url = 'https://api.anthropic.com/v1/messages';

    $data = [
        'model' => $model,
        'max_tokens' => 8192,
        'stream' => true,
        'system' => $systemPrompt,
        'messages' => $messages
    ];

    $ch = curl_init($url);

    $fullContent = '';
    $headers = [];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01'
        ],
        CURLOPT_TIMEOUT => 120,
        CURLOPT_WRITEFUNCTION => function($ch, $chunk) use (&$fullContent, $onChunk) {
            // Parse SSE from Claude
            $lines = explode("\n", $chunk);
            foreach ($lines as $line) {
                if (strpos($line, 'data: ') === 0) {
                    $jsonStr = substr($line, 6);
                    if ($jsonStr === '[DONE]') continue;

                    $event = json_decode($jsonStr, true);
                    if (isset($event['type']) && $event['type'] === 'content_block_delta') {
                        $text = $event['delta']['text'] ?? '';
                        $fullContent .= $text;
                        $onChunk($text);
                    }
                }
            }
            return strlen($chunk);
        },
        CURLOPT_HEADERFUNCTION => function($ch, $header) use (&$headers) {
            $headers[] = $header;
            return strlen($header);
        }
    ]);

    curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => 'Błąd połączenia: ' . $error];
    }

    if ($httpCode !== 200 && empty($fullContent)) {
        return ['success' => false, 'error' => 'Błąd API (HTTP ' . $httpCode . ')'];
    }

    return ['success' => true, 'content' => $fullContent];
}
