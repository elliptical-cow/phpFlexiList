<?php

declare(strict_types=1);

/**
 * Simple PSR-4 autoloader for FTP deployment (replaces Composer autoloader)
 * Maps FlexiList\ namespace to src/ directory
 */

spl_autoload_register(function (string $className): void {
    // Only handle FlexiList namespace
    if (!str_starts_with($className, 'FlexiList\\')) {
        return;
    }

    // Remove namespace prefix
    $classPath = substr($className, strlen('FlexiList\\'));
    
    // Convert namespace separators to directory separators
    $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $classPath);
    
    // Build full file path
    $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $classPath . '.php';
    
    // Load the file if it exists
    if (file_exists($filePath)) {
        require_once $filePath;
    }
});

// Also load our native classes
require_once __DIR__ . '/public/native_classes.php';