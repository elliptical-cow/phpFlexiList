<?php

declare(strict_types=1);

namespace FlexiList\Controllers;

use FlexiList\Services\OpenRouterService;
use NativeRequest;
use NativeResponse;

class SystemController
{
    private OpenRouterService $openRouterService;
    private array $config;

    public function __construct(OpenRouterService $openRouterService, array $config = [])
    {
        $this->openRouterService = $openRouterService;
        $this->config = $config;
    }
    
    /**
     * Detect preferred language from browser Accept-Language header
     */
    private function getLanguageFromBrowser(): string
    {
        $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        // Check if German is preferred (de, de-DE, de-AT, etc.)
        return (preg_match('/^de\b|,\s*de\b/i', $acceptLang)) ? 'de' : 'en';
    }
    
    /**
     * Select appropriate template based on detected language
     */
    private function selectTemplate(string $baseName): string
    {
        $lang = $this->getLanguageFromBrowser();
        $templatePath = __DIR__ . '/../../templates/';
        
        if ($lang === 'de') {
            $germanTemplate = $templatePath . $baseName . '_GER.html';
            if (file_exists($germanTemplate)) {
                return $baseName . '_GER.html';
            }
        }
        
        // Fallback to English template
        return $baseName . '.html';
    }
    
    public function documentation(NativeRequest $request): NativeResponse
    {
        // Get backend URL from configuration
        $backendUrl = $this->config['frontend']['backend_url'] ?? 'http://localhost:8080';
        
        // Select appropriate template based on language
        $templateName = $this->selectTemplate('landing');
        $htmlPath = __DIR__ . '/../../templates/' . $templateName;
        $htmlContent = file_get_contents($htmlPath);
        
        if ($htmlContent === false) {
            // Fallback to embedded HTML (original approach)
            error_log("Failed to read {$templateName} template, using embedded fallback");
            return $this->documentationFallback($backendUrl);
        }
        
        // Use same asset inlining approach as app handler
        $assetsDir = __DIR__ . '/../../assets';
        
        // Read and inline CSS (like app handler does)
        $cssContent = file_get_contents($assetsDir . '/style.css');
        if ($cssContent !== false) {
            $htmlContent = str_replace('<link rel="stylesheet" href="style.css">', '<style>' . $cssContent . '</style>', $htmlContent);
        }
        
        // Generate unique ID for "Create Your List" button
        $uniqueId = uniqid();
        
        // Inject backend configuration script (like app handler)
        $backendScript = "
        <script>
        // Backend integration for landing page
        window.BACKEND_URL = '{$backendUrl}';
        
        // Update backend URL in example
        document.addEventListener('DOMContentLoaded', function() {
            var exampleElement = document.getElementById('backendUrlExample');
            if (exampleElement) {
                exampleElement.textContent = '{$backendUrl}';
            }
            
            // Set unique ID for Create Your List button
            var createBtn = document.getElementById('createListBtn');
            if (createBtn) {
                createBtn.href = '/app?key={$uniqueId}';
            }
        });
        </script>
        ";
        
        // Insert before the closing head tag (like app handler does)
        $htmlContent = str_replace('</head>', $backendScript . '</head>', $htmlContent);
        
        return (new NativeResponse())
            ->write($htmlContent)
            ->withHeader('Content-Type', 'text/html');
    }
    
    private function documentationFallback(string $backendUrl): NativeResponse
    {
        // Original embedded HTML as fallback
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlexiList - Privacy-First Smart Checklists</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6; 
            margin: 0; 
            padding: 0; 
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            min-height: 100vh;
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 40px 20px; 
        }
        .hero {
            text-align: center;
            color: white;
            margin-bottom: 60px;
        }
        .hero h1 { 
            font-size: 3.5rem; 
            margin: 0 0 20px 0; 
            font-weight: 700;
        }
        .hero .subtitle { 
            font-size: 1.3rem; 
            opacity: 0.9; 
            margin-bottom: 40px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        .btn { 
            background: rgba(255,255,255,0.2); 
            color: white; 
            padding: 15px 30px; 
            text-decoration: none; 
            border-radius: 50px; 
            display: inline-block; 
            margin: 10px; 
            font-weight: 600;
            border: 2px solid rgba(255,255,255,0.3);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        .btn:hover { 
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        .btn-primary {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        .btn-primary:hover {
            background: #0056b3;
            border-color: #0056b3;
        }
        /* Abbreviated fallback styles for brevity */
        .features { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 30px; margin: 60px 0; }
        .feature-card { background: white; padding: 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); text-align: center; transition: transform 0.3s ease; border-left: 4px solid #007bff; }
        .feature-card:hover { transform: translateY(-5px); }
        .feature-icon { font-size: 3rem; margin-bottom: 20px; display: block; color: #007bff; }
        .feature-card h3 { color: #0056b3; margin: 0 0 15px 0; font-size: 1.3rem; }
        .feature-card p { color: #666; margin: 0; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="container">
        <div class="hero">
            <h1>📋 FlexiList</h1>
            <div class="subtitle">Template loading failed - using fallback</div>
            <a href="/app?key=demo" class="btn btn-primary">Try Demo List</a>
            <a href="/app?key=' . uniqid() . '" class="btn">Create Your List</a>
        </div>
    </div>
</body>
</html>';

        return (new NativeResponse())
            ->write($html)
            ->withHeader('Content-Type', 'text/html');
    }

    public function impressum(NativeRequest $request): NativeResponse
    {
        $htmlPath = __DIR__ . '/../../templates/impressum.html';
        $htmlContent = file_get_contents($htmlPath);
        
        if ($htmlContent === false) {
            return (new NativeResponse())
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['error' => 'Failed to read impressum.html'], JSON_PRETTY_PRINT));
        }
        
        return (new NativeResponse())
            ->write($htmlContent)
            ->withHeader('Content-Type', 'text/html')
            ->withHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');
    }

    public function guide(NativeRequest $request): NativeResponse
    {
        // Select appropriate template based on language
        $templateName = $this->selectTemplate('guide');
        $htmlPath = __DIR__ . '/../../templates/' . $templateName;
        $htmlContent = file_get_contents($htmlPath);
        
        if ($htmlContent === false) {
            return (new NativeResponse())
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['error' => "Failed to read {$templateName}"], JSON_PRETTY_PRINT));
        }
        
        return (new NativeResponse())
            ->write($htmlContent)
            ->withHeader('Content-Type', 'text/html')
            ->withHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');
    }

    public function healthCheck(NativeRequest $request): NativeResponse
    {
        $apiKey = $this->config['openrouter']['api_key'] ?? '';
        $openRouterConfigured = !empty($apiKey) && $apiKey !== 'your_api_key_here';

        $health = [
            'status' => 'healthy',
            'version' => '2.0.0-ftp',
            'deployment' => 'FTP-Ready (No Composer Dependencies)',
            'services' => [
                'checklist' => 'operational',
                'openrouter' => $openRouterConfigured ? 'configured' : 'not_configured'
            ]
        ];

        return (new NativeResponse())
            ->write(json_encode($health, JSON_UNESCAPED_UNICODE))
            ->withHeader('Content-Type', 'application/json');
    }

    public function datenschutz(NativeRequest $request): NativeResponse
    {
        $htmlPath = __DIR__ . '/../../templates/datenschutz.html';
        $htmlContent = file_get_contents($htmlPath);
        
        if ($htmlContent === false) {
            return (new NativeResponse())
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['error' => 'Failed to read datenschutz.html'], JSON_PRETTY_PRINT));
        }
        
        return (new NativeResponse())
            ->write($htmlContent)
            ->withHeader('Content-Type', 'text/html')
            ->withHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');
    }

    public function nutzungsbedingungen(NativeRequest $request): NativeResponse
    {
        $htmlPath = __DIR__ . '/../../templates/nutzungsbedingungen.html';
        $htmlContent = file_get_contents($htmlPath);
        
        if ($htmlContent === false) {
            return (new NativeResponse())
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['error' => 'Failed to read nutzungsbedingungen.html'], JSON_PRETTY_PRINT));
        }
        
        return (new NativeResponse())
            ->write($htmlContent)
            ->withHeader('Content-Type', 'text/html')
            ->withHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');
    }

    public function testOpenRouter(NativeRequest $request): NativeResponse
    {
        try {
            $result = $this->openRouterService->testConnection();
            return (new NativeResponse())
                ->write(json_encode($result, JSON_UNESCAPED_UNICODE))
                ->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $error = [
                'success' => false,
                'error' => 'Test failed',
                'detail' => $e->getMessage()
            ];
            return (new NativeResponse())
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->write(json_encode($error, JSON_UNESCAPED_UNICODE));
        }
    }
}