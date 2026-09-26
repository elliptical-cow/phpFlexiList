<?php

declare(strict_types=1);

/**
 * Load secrets from separate file
 * 
 * The secrets.php file contains sensitive configuration (API keys, passwords, etc.)
 * that should not be committed to version control.
 * 
 * To set up secrets:
 * 1. Copy config/secrets.php.example to config/secrets.php
 * 2. Update config/secrets.php with your actual API keys
 * 3. The secrets.php file is automatically excluded from git
 */
$secrets = [];
$secretsPath = __DIR__ . '/secrets.php';
if (file_exists($secretsPath)) {
    $secrets = require $secretsPath;
}

return [
    'settings' => [
        'displayErrorDetails' => false,
        'logError' => true,
        'logErrorDetails' => true,
        'logger' => [
            'name' => 'flexilist-api',
            'path' => 'backend_logs.log',
            'level' => 'debug',  // Simple string instead of Monolog::Level
        ],
        'data' => [
            'dir' => '../data',
        ],
        'frontend' => [
            'dir' => realpath(__DIR__ . '/../../'),
            'backend_url' => 'http://localhost:8080', #'http://localhost:8080', 'http://localhost:8080'
        ],
        'cors' => [
            'allowed_origins' => ['http://localhost:8080', 'http://localhost:8080',  'http://localhost:8080'],
            'allow_credentials' => true,
            'environment' => 'production',
        ],
        'debug' => [
            'enabled' => false,
        ],
        'openrouter' => [
            'api_key' => $secrets['openrouter']['api_key'] ?? 'your-api-key-here',
            'model' => 'google/gemini-2.5-flash-lite-preview-09-2025',
            'max_context' => 5000,
            'timeout' => 30,
            'base_url' => 'https://openrouter.ai/api/v1',
            'debug' => false,
        ],
    ],
];
