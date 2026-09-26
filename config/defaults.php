<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'FlexiList',
        'version' => '0.1.0-dev',
        'default_language' => 'en',
    ],
    'paths' => [
        'data' => dirname(__DIR__) . '/data',
        'templates' => dirname(__DIR__) . '/templates',
        'site_templates' => null,
        'site_css' => null,
        'log' => dirname(__DIR__) . '/var/flexilist.log',
        'rate_limits' => dirname(__DIR__) . '/var/rate-limits',
    ],
    'frontend' => [
        // Empty means same origin as the web application.
        'backend_url' => '',
    ],
    'features' => [
        'auto_categorization' => false,
        'autocomplete' => true,
    ],
    'cors' => [
        // Same-origin requests need no CORS header. Add exact origins in site.php.
        'allowed_origins' => [],
    ],
    'logging' => [
        'enabled' => false,
    ],
    'security' => [
        'max_request_bytes' => 1048576,
        'create_limit_per_hour' => 30,
        'ai_limit_per_minute' => 10,
    ],
    'openrouter' => [
        'api_key' => null,
        'model' => 'google/gemini-2.5-flash-lite',
        'max_context' => 5000,
        'timeout' => 30,
        'base_url' => 'https://openrouter.ai/api/v1',
        'debug' => false,
    ],
    'site' => [
        // Additional routes supplied by a deployment, for example legal pages.
        // Format: '/privacy' => ['template' => 'privacy', 'indexable' => false]
        'pages' => [],
    ],
];
