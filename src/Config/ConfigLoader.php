<?php

declare(strict_types=1);

namespace FlexiList\Config;

use RuntimeException;

final class ConfigLoader
{
    public static function load(string $projectRoot): array
    {
        $defaults = require $projectRoot . '/config/defaults.php';
        $siteConfigPath = getenv('FLEXILIST_SITE_CONFIG') ?: $projectRoot . '/config/site.php';
        $siteConfig = [];

        if (is_file($siteConfigPath)) {
            $siteConfig = require $siteConfigPath;
            if (!is_array($siteConfig)) {
                throw new RuntimeException('Site configuration must return an array');
            }
        }

        $config = array_replace_recursive($defaults, $siteConfig);
        self::applyEnvironment($config);
        self::validate($config);

        return $config;
    }

    private static function applyEnvironment(array &$config): void
    {
        $map = [
            'FLEXILIST_APP_NAME' => ['app', 'name'],
            'FLEXILIST_BACKEND_URL' => ['frontend', 'backend_url'],
            'FLEXILIST_DATA_DIR' => ['paths', 'data'],
            'FLEXILIST_SITE_TEMPLATES' => ['paths', 'site_templates'],
            'FLEXILIST_SITE_CSS' => ['paths', 'site_css'],
            'FLEXILIST_LOG_PATH' => ['paths', 'log'],
            'OPENROUTER_API_KEY' => ['openrouter', 'api_key'],
            'OPENROUTER_MODEL' => ['openrouter', 'model'],
        ];

        foreach ($map as $variable => [$section, $key]) {
            $value = getenv($variable);
            if ($value !== false && $value !== '') {
                $config[$section][$key] = $value;
            }
        }

        $autoCategorization = getenv('FLEXILIST_AUTO_CATEGORIZATION');
        if ($autoCategorization !== false) {
            $config['features']['auto_categorization'] = filter_var(
                $autoCategorization,
                FILTER_VALIDATE_BOOL
            );
        }
    }

    private static function validate(array $config): void
    {
        foreach (['app', 'paths', 'frontend', 'features', 'cors', 'security', 'openrouter', 'site'] as $section) {
            if (!isset($config[$section]) || !is_array($config[$section])) {
                throw new RuntimeException("Missing configuration section: {$section}");
            }
        }

        if (!is_string($config['app']['name'] ?? null) || trim($config['app']['name']) === '') {
            throw new RuntimeException('Application name must not be empty');
        }

        if (!is_array($config['cors']['allowed_origins'] ?? null)) {
            throw new RuntimeException('cors.allowed_origins must be an array');
        }
    }
}
