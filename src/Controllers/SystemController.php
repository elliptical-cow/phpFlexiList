<?php

declare(strict_types=1);

namespace FlexiList\Controllers;

use FlexiList\Services\TemplateRenderer;
use NativeRequest;
use NativeResponse;
use Throwable;

final class SystemController
{
    public function __construct(
        private readonly array $config,
        private readonly TemplateRenderer $templates
    ) {}

    public function page(NativeRequest $request, string $template, bool $indexable = true): NativeResponse
    {
        try {
            $html = $this->templates->render($template, $request->getHeaderLine('Accept-Language'));
            $response = (new NativeResponse())
                ->write($html)
                ->withHeader('Content-Type', 'text/html; charset=utf-8');

            return $indexable
                ? $response
                : $response->withHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        } catch (Throwable $error) {
            error_log('Template rendering failed: ' . $error->getMessage());
            return (new NativeResponse())
                ->withStatus(404)
                ->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['error' => 'Page not found']));
        }
    }

    public function healthCheck(NativeRequest $request): NativeResponse
    {
        $health = [
            'status' => 'healthy',
            'name' => $this->config['app']['name'],
            'version' => $this->config['app']['version'],
            'features' => [
                'auto_categorization' => (bool) $this->config['features']['auto_categorization'],
            ],
        ];

        return (new NativeResponse())
            ->write(json_encode($health, JSON_UNESCAPED_UNICODE))
            ->withHeader('Content-Type', 'application/json');
    }
}
