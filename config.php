<?php
/**
 * Mockery - AI Page Builder Configuration
 */

// Load .env file if exists
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue; // Skip comments
        if (strpos($line, '=') === false) continue;

        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Remove quotes if present
        if (preg_match('/^["\'](.*)["\']\s*$/', $value, $matches)) {
            $value = $matches[1];
        }

        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

return [
    // Claude API Configuration
    'claude_api_key' => getenv('CLAUDE_API_KEY') ?: 'YOUR_CLAUDE_API_KEY_HERE',
    'claude_model' => 'claude-sonnet-4-5',

    // Paths
    'pages_dir' => __DIR__ . '/pages',

    // Security
    'allowed_origins' => ['*'],

    // Rate limiting (requests per minute)
    'rate_limit' => 30,
];
