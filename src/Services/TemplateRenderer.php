<?php

declare(strict_types=1);

namespace FlexiList\Services;

use RuntimeException;

final class TemplateRenderer
{
    public function __construct(
        private readonly string $defaultDirectory,
        private readonly ?string $overrideDirectory,
        private readonly array $tokens
    ) {}

    public function render(string $template, string $acceptLanguage = ''): string
    {
        if (!preg_match('/^[a-zA-Z0-9_\/-]+$/', $template)) {
            throw new RuntimeException('Invalid template name');
        }

        $language = preg_match('/(^|,)\s*de(?:[-_][A-Z]{2})?\b/i', $acceptLanguage) ? 'de' : 'en';
        $candidates = $language === 'en'
            ? ["{$template}.html"]
            : ["{$template}.{$language}.html", "{$template}.html"];

        foreach (array_filter([$this->overrideDirectory, $this->defaultDirectory]) as $directory) {
            foreach ($candidates as $candidate) {
                $path = rtrim($directory, '/') . '/' . $candidate;
                if (is_file($path)) {
                    $content = file_get_contents($path);
                    if ($content === false) {
                        throw new RuntimeException("Unable to read template: {$candidate}");
                    }
                    return strtr($content, $this->tokens);
                }
            }
        }

        throw new RuntimeException("Template not found: {$template}");
    }
}

