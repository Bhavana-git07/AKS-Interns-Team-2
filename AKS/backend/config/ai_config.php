<?php
// config/ai_config.php

// Define configuration details for the AI Assistant.
// API Keys are loaded from environment variables or a local .env file.
// DO NOT hardcode keys here.

$ai_api_key = getenv('AI_API_KEY') ?: null;
$ai_provider = getenv('AI_PROVIDER') ?: 'mock'; // options: 'gemini', 'mock'

// Check for local .env file in the backend directory
$env_path = __DIR__ . '/../.env';
if (file_exists($env_path)) {
    $env_lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env_lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove optional surrounding quotes
            $value = trim($value, '"\'');
            
            if ($key === 'AI_API_KEY') {
                $ai_api_key = $value;
                $ai_provider = 'gemini';
            } elseif ($key === 'AI_PROVIDER') {
                $ai_provider = $value;
            }
        }
    }
}
?>
