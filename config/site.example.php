<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'My FlexiList',
    ],
    'frontend' => [
        'backend_url' => 'https://lists.example.com',
    ],
    'cors' => [
        'allowed_origins' => ['https://lists.example.com'],
    ],
    'paths' => [
        'site_templates' => __DIR__ . '/../site/templates',
        'site_css' => __DIR__ . '/../site/theme.css',
    ],
    'site' => [
        'pages' => [
            '/privacy' => ['template' => 'privacy', 'indexable' => false],
            '/imprint' => ['template' => 'imprint', 'indexable' => false],
        ],
    ],
];

