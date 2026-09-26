<?php

declare(strict_types=1);


// FTP-ready version - no Composer dependencies required
require __DIR__ . '/../autoload.php';

use FlexiList\Controllers\ChecklistController;
use FlexiList\Controllers\SystemController;
use FlexiList\Services\ChecklistService;
use FlexiList\Services\OpenRouterService;
use FlexiList\Models\PatchMetrics;

// Basic logging function
function logRequest(string $method, string $path, int $statusCode = 200): void {
    static $debugEnabled = null;
    static $logFile = null;
    
    if ($debugEnabled === null) {
        global $config;
        $debugEnabled = $config['debug']['enabled'] ?? false;
        $logFile = $config['logger']['path'] ?? 'backend_logs.log';
    }
    
    if (!$debugEnabled || str_starts_with($path, '/style.css') || str_starts_with($path, '/app.js') || str_starts_with($path, '/components/')) {
        return;
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $message = "[{$timestamp}] [{$method}] [{$path}] Status: {$statusCode}\n";
    
    try {
        file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX);
    } catch (\Exception $e) {
        error_log("Failed to write log: " . $e->getMessage());
    }
}

// Basic CORS headers function
function setCorsHeaders(): void {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept, Origin, Authorization, If-Match');
    header('Access-Control-Expose-Headers: ETag, Retry-After');
}

// Handle CORS preflight
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    setCorsHeaders();
    http_response_code(200);
    exit();
}

// Set CORS headers for all requests
setCorsHeaders();

// Load configuration
$settings = require __DIR__ . '/../config/settings.php';
$config = $settings['settings'];

// Create services (manual dependency injection - no Composer needed)
$checklistService = new ChecklistService($config['data']['dir']);
$openRouterService = new OpenRouterService($config['openrouter'], $config['frontend']['backend_url']);
$patchMetrics = new PatchMetrics();

// Create controllers
$checklistController = new ChecklistController($checklistService, $openRouterService, $patchMetrics);
$systemController = new SystemController($openRouterService, $config);

// Create router
$router = new NativeRouter();

// Route definitions

// Documentation route
$router->get('/', function(NativeRequest $request) use ($systemController): NativeResponse {
    return $systemController->documentation($request);
});

// Impressum route
$router->get('/impressum', function(NativeRequest $request) use ($systemController): NativeResponse {
    return $systemController->impressum($request);
});

// Guide route
$router->get('/guide', function(NativeRequest $request) use ($systemController): NativeResponse {
    return $systemController->guide($request);
});

// Datenschutz route
$router->get('/datenschutz', function(NativeRequest $request) use ($systemController): NativeResponse {
    return $systemController->datenschutz($request);
});

// Nutzungsbedingungen route
$router->get('/nutzungsbedingungen', function(NativeRequest $request) use ($systemController): NativeResponse {
    return $systemController->nutzungsbedingungen($request);
});

// Frontend app routes (inline all assets to avoid routing conflicts)
$appHandler = function(NativeRequest $request) use ($config): NativeResponse {
    
    $backendUrl = $config['frontend']['backend_url'];
    
    // Use relative paths to assets and config
    $assetsDir = __DIR__ . '/../assets';
    $configDir = __DIR__ . '/../config';
    
    // Language detection (same logic as SystemController)
    $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    $isGerman = preg_match('/^de\b|,\s*de\b/i', $acceptLang);
    
    // Select appropriate template
    $templateName = $isGerman ? 'index_GER.html' : 'index.html';
    $htmlPath = __DIR__ . '/../templates/' . $templateName;
    
    // Check if German template exists, fallback to English
    if ($isGerman && !file_exists($htmlPath)) {
        $templateName = 'index.html';
        $htmlPath = __DIR__ . '/../templates/' . $templateName;
    }
    
    $htmlContent = file_get_contents($htmlPath);
    
    // Check if file reading failed
    if ($htmlContent === false) {
        return (new NativeResponse())
            ->withStatus(500)
            ->withHeader('Content-Type', 'application/json')
            ->write(json_encode(['error' => "Failed to read {$templateName}"], JSON_PRETTY_PRINT));
    }
    
    // Read and inline CSS
    $cssContent = file_get_contents($assetsDir . '/style.css');
    $htmlContent = str_replace('<link rel="stylesheet" href="style.css">', '<style>' . $cssContent . '</style>', $htmlContent);
    
    // Read and inline JavaScript files
    $configJs = file_get_contents($configDir . '/config.js');
    $translationsJs = file_get_contents($configDir . '/translations.js');
    $backendServiceJs = file_get_contents($assetsDir . '/services/BackendService.js');
    $jsonOperationsJs = file_get_contents($assetsDir . '/utils/jsonOperations.js');
    $checklistOperationsJs = file_get_contents($assetsDir . '/utils/checklistOperations.js');
    $backendOperationsJs = file_get_contents($assetsDir . '/utils/backendOperations.js');
    $autoCompleteInputJs = file_get_contents($assetsDir . '/components/AutoCompleteInput.js');
    $moveModalJs = file_get_contents($assetsDir . '/components/MoveModal.js');
    $listItemJs = file_get_contents($assetsDir . '/components/ListItem.js');
    $appJs = file_get_contents($assetsDir . '/app.js');
    
    // Detect language (same logic as SystemController)  
    $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    $detectedLanguage = preg_match('/^de\b|,\s*de\b/i', $acceptLang) ? 'de' : 'en';
    
    // Inject language detection BEFORE translations system to fix timing issue
    $languageScript = '<script>window.DETECTED_LANGUAGE = \'' . $detectedLanguage . '\';</script>';
    
    // Replace script tags with inline content, ensuring language is available for translations
    $htmlContent = str_replace('<script src="config.js"></script>', 
        '<script>' . $configJs . '</script>' . $languageScript . '<script>' . $translationsJs . '</script>', 
        $htmlContent);
    $htmlContent = str_replace('<script src="services/BackendService.js"></script>', '<script>' . $backendServiceJs . '</script>', $htmlContent);
    $htmlContent = str_replace('<script src="utils/jsonOperations.js"></script>', '<script>' . $jsonOperationsJs . '</script>', $htmlContent);
    $htmlContent = str_replace('<script src="utils/checklistOperations.js"></script>', '<script>' . $checklistOperationsJs . '</script>', $htmlContent);
    $htmlContent = str_replace('<script src="utils/backendOperations.js"></script>', '<script>' . $backendOperationsJs . '</script>', $htmlContent);
    $htmlContent = str_replace('<script src="components/AutoCompleteInput.js"></script>', '<script>' . $autoCompleteInputJs . '</script>', $htmlContent);
    $htmlContent = str_replace('<script src="components/MoveModal.js"></script>', '<script>' . $moveModalJs . '</script>', $htmlContent);
    $htmlContent = str_replace('<script src="components/ListItem.js"></script>', '<script>' . $listItemJs . '</script>', $htmlContent);
    // Handle app.js inlining in the dynamic loading context
    // Use JSON encoding to safely escape the JavaScript content
    $escapedAppJs = json_encode($appJs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $htmlContent = str_replace(
        'appScript.src = \'app.js\';', 
        'appScript.text = ' . $escapedAppJs . ';', 
        $htmlContent
    );
    
    // Inject backend integration script (language already injected earlier)
    $backendScript = "
        <script>
        // Backend integration
        window.BACKEND_URL = '{$backendUrl}';
        window.LIST_ID = new URLSearchParams(window.location.search).get('key') || new URLSearchParams(window.location.search).get('id');
        </script>
        ";
    
    // Insert before the closing head tag
    $htmlContent = str_replace('</head>', $backendScript . '</head>', $htmlContent);
    
    return (new NativeResponse())
        ->write($htmlContent)
        ->withHeader('Content-Type', 'text/html');
};

// Register the app handler for multiple routes
$router->get('/app', $appHandler);        // Original route (fixed with .htaccess)
$router->get('/checklist', $appHandler);
$router->get('/list', $appHandler);
$router->get('/webapp', $appHandler);

// API routes
$router->get('/api/list/{list_key}', function(NativeRequest $request) use ($checklistController): NativeResponse {
    return $checklistController->getChecklist($request);
});

$router->put('/api/list/{list_key}', function(NativeRequest $request) use ($checklistController): NativeResponse {
    return $checklistController->updateChecklist($request);
});

$router->patch('/api/list/{list_key}', function(NativeRequest $request) use ($checklistController): NativeResponse {
    return $checklistController->patchChecklist($request);
});

$router->delete('/api/list/{list_key}', function(NativeRequest $request) use ($checklistController): NativeResponse {
    return $checklistController->deleteChecklist($request);
});

$router->get('/api/list/{list_key}/exists', function(NativeRequest $request) use ($checklistController): NativeResponse {
    return $checklistController->checkListExists($request);
});

$router->get('/api/list/{list_key}/stats', function(NativeRequest $request) use ($checklistController): NativeResponse {
    return $checklistController->getListStats($request);
});

$router->post('/api/list/{list_key}/auto-categorize', function(NativeRequest $request) use ($checklistController): NativeResponse {
    return $checklistController->autoCategorizeItems($request);
});


// System routes
$router->get('/api/health', function(NativeRequest $request) use ($systemController): NativeResponse {
    return $systemController->healthCheck($request);
});

$router->get('/api/test-openrouter', function(NativeRequest $request) use ($systemController): NativeResponse {
    return $systemController->testOpenRouter($request);
});

$router->get('/api/metrics/patch', function(NativeRequest $request) use ($checklistController): NativeResponse {
    return $checklistController->getPatchMetrics($request);
});

// Create request from globals
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$query = $_GET ?? [];
$headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
$body = file_get_contents('php://input') ?: '';

$request = new NativeRequest($method, $path, $query, $headers, $body);

// Dispatch request
$response = $router->dispatch($request);

if ($response === null) {
    // 404 Not Found
    $response = (new NativeResponse())
        ->withStatus(404)
        ->withHeader('Content-Type', 'application/json')
        ->write(json_encode([
            'error' => 'Not Found',
            'detail' => 'Endpoint not found',
            'path' => $path
        ], JSON_UNESCAPED_UNICODE));
}

// Log the request
logRequest($method, $path, $response->getStatusCode());

// Send response
$response->send();