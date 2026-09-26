<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use FlexiList\Config\ConfigLoader;
use FlexiList\Controllers\ChecklistController;
use FlexiList\Controllers\SystemController;
use FlexiList\Models\PatchMetrics;
use FlexiList\Services\ChecklistService;
use FlexiList\Services\OpenRouterService;
use FlexiList\Services\TemplateRenderer;
use FlexiList\Security\AccessDeniedException;
use FlexiList\Security\ListAccessService;
use FlexiList\Security\RateLimiter;

$root = dirname(__DIR__);
$config = ConfigLoader::load($root);
$backendUrl = rtrim((string) $config['frontend']['backend_url'], '/');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store');
$connectSources = ["'self'"];
$backendParts = parse_url($backendUrl);
if (is_array($backendParts) && in_array($backendParts['scheme'] ?? '', ['http', 'https'], true) && isset($backendParts['host'])) {
    $backendOrigin = $backendParts['scheme'] . '://' . $backendParts['host'];
    if (isset($backendParts['port'])) {
        $backendOrigin .= ':' . $backendParts['port'];
    }
    $connectSources[] = $backendOrigin;
}
header(
    "Content-Security-Policy: default-src 'self'; "
    . "script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; "
    . "img-src 'self' data:; connect-src " . implode(' ', array_unique($connectSources)) . "; "
    . "object-src 'none'; base-uri 'self'; frame-ancestors 'none'"
);

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, $config['cors']['allowed_origins'], true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, If-Match');
    header('Access-Control-Expose-Headers: ETag, Retry-After');
}
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code($origin !== '' && in_array($origin, $config['cors']['allowed_origins'], true) ? 204 : 403);
    exit;
}

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > $config['security']['max_request_bytes']) {
    http_response_code(413);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Request body is too large']);
    exit;
}

$templates = new TemplateRenderer(
    $config['paths']['templates'],
    $config['paths']['site_templates'],
    [
        '{{ appName }}' => htmlspecialchars($config['app']['name'], ENT_QUOTES, 'UTF-8'),
        '{{ appVersion }}' => htmlspecialchars($config['app']['version'], ENT_QUOTES, 'UTF-8'),
        '{{ backendUrl }}' => htmlspecialchars($backendUrl, ENT_QUOTES, 'UTF-8'),
    ]
);

$checklistService = new ChecklistService($config['paths']['data']);
$listAccess = new ListAccessService($config['paths']['data']);
$rateLimiter = new RateLimiter($config['paths']['rate_limits']);
$openRouterService = new OpenRouterService($config['openrouter'], $backendUrl);
$checklistController = new ChecklistController($checklistService, $openRouterService, new PatchMetrics());
$systemController = new SystemController($config, $templates);
$router = new NativeRouter();

$unauthorized = static fn(string $message = 'Unauthorized'): NativeResponse => (new NativeResponse())
    ->withStatus(401)
    ->withHeader('Content-Type', 'application/json')
    ->withHeader('WWW-Authenticate', 'Bearer')
    ->write(json_encode(['error' => $message]));

$authorized = static function (callable $handler) use ($listAccess, $unauthorized): callable {
    return static function (NativeRequest $request) use ($listAccess, $handler, $unauthorized): NativeResponse {
        try {
            $listAccess->assertAuthorized(
                (string) $request->getPathParam('list_key'),
                $request->getHeaderLine('Authorization')
            );
            return $handler($request);
        } catch (AccessDeniedException) {
            return $unauthorized();
        }
    };
};

$router->get('/', fn(NativeRequest $request): NativeResponse => $systemController->page($request, 'landing'));
$router->get('/guide', fn(NativeRequest $request): NativeResponse => $systemController->page($request, 'guide'));

foreach ($config['site']['pages'] as $route => $page) {
    $router->get($route, fn(NativeRequest $request): NativeResponse => $systemController->page(
        $request,
        (string) $page['template'],
        (bool) ($page['indexable'] ?? false)
    ));
}

$appHandler = function (NativeRequest $request) use ($config, $templates, $backendUrl, $root): NativeResponse {
    try {
        $html = $templates->render('index', $request->getHeaderLine('Accept-Language'));

        $css = file_get_contents($root . '/assets/style.css') ?: '';
        $siteCssPath = $config['paths']['site_css'];
        if (is_string($siteCssPath) && is_file($siteCssPath)) {
            $css .= "\n" . (file_get_contents($siteCssPath) ?: '');
        }
        $html = str_replace('<link rel="stylesheet" href="style.css">', '<style>' . $css . '</style>', $html);

        $frontendConfig = [
            'BACKEND_URL' => $backendUrl,
            'APP_NAME' => $config['app']['name'],
            'VERSION' => $config['app']['version'],
            'FEATURES' => [
                'DEBUG_MODE' => false,
                'SHOW_PATCH_DEBUG' => false,
                'SHOW_JSON_VIEW' => false,
                'AUTO_CATEGORIZATION' => (bool) $config['features']['auto_categorization'],
            ],
            'AUTO_COMPLETE' => [
                'ENABLED' => (bool) $config['features']['autocomplete'],
                'MIN_CHARS' => 3,
                'MAX_SUGGESTIONS' => 5,
                'SHOW_CHECKED_FIRST' => true,
                'DEBOUNCE_MS' => 150,
                'CASE_SENSITIVE' => false,
            ],
            'UI_SETTINGS' => ['HIERARCHY_INDENTATION' => 20],
        ];
        $configScript = 'window.FLEXI_CONFIG = ' . json_encode(
            $frontendConfig,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
        ) . ';';
        $language = preg_match('/(^|,)\s*de\b/i', $request->getHeaderLine('Accept-Language')) ? 'de' : 'en';
        $languageScript = 'window.DETECTED_LANGUAGE = ' . json_encode($language) . ';';
        $translations = file_get_contents($root . '/config/translations.js') ?: '';
        $html = str_replace(
            '<script src="config.js"></script>',
            '<script>' . $configScript . $languageScript . $translations . '</script>',
            $html
        );

        $scripts = [
            'services/BackendService.js' => '/assets/services/BackendService.js',
            'utils/jsonOperations.js' => '/assets/utils/jsonOperations.js',
            'utils/checklistOperations.js' => '/assets/utils/checklistOperations.js',
            'utils/backendOperations.js' => '/assets/utils/backendOperations.js',
            'components/AutoCompleteInput.js' => '/assets/components/AutoCompleteInput.js',
            'components/MoveModal.js' => '/assets/components/MoveModal.js',
            'components/ListItem.js' => '/assets/components/ListItem.js',
        ];
        foreach ($scripts as $source => $path) {
            $script = file_get_contents($root . $path) ?: '';
            $html = str_replace('<script src="' . $source . '"></script>', '<script>' . $script . '</script>', $html);
        }

        $app = file_get_contents($root . '/assets/app.js') ?: '';
        $html = str_replace(
            "appScript.src = 'app.js';",
            'appScript.text = ' . json_encode(
                $app,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
            ) . ';',
            $html
        );

        $runtime = '<script>window.BACKEND_URL = window.FLEXI_CONFIG.BACKEND_URL || window.location.origin;</script>';
        $html = str_replace('</head>', $runtime . '</head>', $html);

        return (new NativeResponse())
            ->write($html)
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
    } catch (Throwable $error) {
        error_log('Application rendering failed: ' . $error->getMessage());
        return (new NativeResponse())
            ->withStatus(500)
            ->withHeader('Content-Type', 'application/json')
            ->write(json_encode(['error' => 'Application could not be rendered']));
    }
};

foreach (['/app', '/checklist', '/list', '/webapp'] as $route) {
    $router->get($route, $appHandler);
}

$router->post('/api/lists', function (NativeRequest $request) use (
    $rateLimiter,
    $config,
    $listAccess,
    $checklistService
): NativeResponse {
    $client = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!$rateLimiter->consume(
        'create:' . $client,
        (int) $config['security']['create_limit_per_hour'],
        3600
    )) {
        return (new NativeResponse())
            ->withStatus(429)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Retry-After', '3600')
            ->write(json_encode(['error' => 'Too many lists created']));
    }

    $credentials = $listAccess->issue();
    try {
        $checklistService->createChecklist($credentials['id']);
    } catch (Throwable $error) {
        $listAccess->revoke($credentials['id']);
        error_log('List creation failed: ' . $error->getMessage());
        return (new NativeResponse())
            ->withStatus(500)
            ->withHeader('Content-Type', 'application/json')
            ->write(json_encode(['error' => 'List could not be created']));
    }

    return (new NativeResponse())
        ->withStatus(201)
        ->withHeader('Content-Type', 'application/json')
        ->withHeader('Cache-Control', 'no-store')
        ->write(json_encode($credentials, JSON_UNESCAPED_SLASHES));
});

$router->get('/api/list/{list_key}', $authorized(
    fn(NativeRequest $request): NativeResponse => $checklistController->getChecklist($request)
));
$router->put('/api/list/{list_key}', $authorized(
    fn(NativeRequest $request): NativeResponse => $checklistController->updateChecklist($request)
));
$router->patch('/api/list/{list_key}', $authorized(
    fn(NativeRequest $request): NativeResponse => $checklistController->patchChecklist($request)
));
$router->delete('/api/list/{list_key}', $authorized(
    function (NativeRequest $request) use ($checklistController, $listAccess): NativeResponse {
        $response = $checklistController->deleteChecklist($request);
        if ($response->getStatusCode() === 200) {
            $listAccess->revoke((string) $request->getPathParam('list_key'));
        }
        return $response;
    }
));
$router->get('/api/list/{list_key}/stats', $authorized(
    fn(NativeRequest $request): NativeResponse => $checklistController->getListStats($request)
));

if ($config['features']['auto_categorization']) {
    $router->post('/api/list/{list_key}/auto-categorize', $authorized(
        function (NativeRequest $request) use ($checklistController, $rateLimiter, $config): NativeResponse {
            $listId = (string) $request->getPathParam('list_key');
            if (!$rateLimiter->consume(
                'ai:' . $listId,
                (int) $config['security']['ai_limit_per_minute'],
                60
            )) {
                return (new NativeResponse())
                    ->withStatus(429)
                    ->withHeader('Content-Type', 'application/json')
                    ->withHeader('Retry-After', '60')
                    ->write(json_encode(['error' => 'AI rate limit exceeded']));
            }
            return $checklistController->autoCategorizeItems($request);
        }
    ));
}

$router->get('/api/health', fn(NativeRequest $request): NativeResponse => $systemController->healthCheck($request));

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$request = new NativeRequest(
    $method,
    $path,
    $_GET ?? [],
    function_exists('getallheaders') ? (getallheaders() ?: []) : [],
    file_get_contents('php://input') ?: ''
);
$response = $router->dispatch($request) ?? (new NativeResponse())
    ->withStatus(404)
    ->withHeader('Content-Type', 'application/json')
    ->write(json_encode(['error' => 'Not Found']));
$response->send();
