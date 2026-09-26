<?php

declare(strict_types=1);

namespace FlexiList\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class StaticController
{
    private string $frontendDir;
    private string $backendUrl;

    public function __construct(string $frontendDir, string $backendUrl)
    {
        $this->frontendDir = $frontendDir;
        $this->backendUrl = $backendUrl;
    }

    public function serveApp(Request $request, Response $response): Response
    {
        try {
            $htmlPath = $this->frontendDir . '/index.html';
            if (!file_exists($htmlPath)) {
                $error = ['error' => 'Not Found', 'detail' => 'Frontend files not found'];
                $response->getBody()->write(json_encode($error, JSON_UNESCAPED_UNICODE));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $htmlContent = file_get_contents($htmlPath);

            // Inject backend integration script
            $backendScript = "
        <script>
        // Backend integration
        window.BACKEND_URL = '{$this->backendUrl}';
        window.LIST_ID = new URLSearchParams(window.location.search).get('key') || new URLSearchParams(window.location.search).get('id');
        </script>
        ";

            // Insert before the closing head tag
            $htmlContent = str_replace('</head>', $backendScript . '</head>', $htmlContent);

            $response->getBody()->write($htmlContent);
            return $response->withHeader('Content-Type', 'text/html');

        } catch (Exception $e) {
            $error = ['error' => 'Not Found', 'detail' => 'Frontend files not found'];
            $response->getBody()->write(json_encode($error, JSON_UNESCAPED_UNICODE));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
    }

    public function serveCss(Request $request, Response $response): Response
    {
        return $this->serveStaticFile('style.css', 'text/css', $response);
    }

    public function serveJs(Request $request, Response $response): Response
    {
        return $this->serveStaticFile('app.js', 'application/javascript', $response);
    }

    public function serveBackendServiceJs(Request $request, Response $response): Response
    {
        return $this->serveStaticFile('services/BackendService.js', 'application/javascript', $response);
    }

    public function serveMoveModalJs(Request $request, Response $response): Response
    {
        return $this->serveStaticFile('components/MoveModal.js', 'application/javascript', $response);
    }

    public function serveListItemJs(Request $request, Response $response): Response
    {
        return $this->serveStaticFile('components/ListItem.js', 'application/javascript', $response);
    }

    public function serveConfigJs(Request $request, Response $response): Response
    {
        return $this->serveStaticFile('config.js', 'application/javascript', $response);
    }

    private function serveStaticFile(string $relativePath, string $contentType, Response $response): Response
    {
        try {
            $filePath = $this->frontendDir . '/' . $relativePath;
            if (!file_exists($filePath)) {
                $error = ['error' => 'Not Found', 'detail' => $relativePath . ' file not found'];
                $response->getBody()->write(json_encode($error, JSON_UNESCAPED_UNICODE));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $content = file_get_contents($filePath);
            $response->getBody()->write($content);
            return $response->withHeader('Content-Type', $contentType);

        } catch (Exception $e) {
            $error = ['error' => 'Not Found', 'detail' => $relativePath . ' file not found'];
            $response->getBody()->write(json_encode($error, JSON_UNESCAPED_UNICODE));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
    }
}