<?php

declare(strict_types=1);

// FTP/shared-hosting fallback when server environment variables are not
// available. Copy to config/secrets.php, fill it locally, and never commit it.
// The web server document root must remain public/ so this file is unreachable.
return [
    'paths' => [
        'data' => '/absolute/private/path/to/data',
        'rate_limits' => '/absolute/private/path/to/rate-limits',
    ],
    'openrouter' => [
        'api_key' => 'replace-at-deploy-time',
        'model' => 'google/gemini-2.5-flash-lite',
    ],
];
