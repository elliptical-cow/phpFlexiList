<?php

declare(strict_types=1);

namespace FlexiList\Utils;

use Exception;

class JsonPatch
{
    /**
     * Apply JSON Patch operations to data
     * Implements basic RFC 6902 operations
     */
    public static function apply(array $data, array $operations): array
    {
        $result = $data;
        
        foreach ($operations as $operation) {
            $result = self::applyOperation($result, $operation);
        }
        
        return $result;
    }
    
    private static function applyOperation(array $data, array $operation): array
    {
        $op = $operation['op'] ?? '';
        $path = $operation['path'] ?? '';
        
        switch ($op) {
            case 'add':
                return self::add($data, $path, $operation['value']);
            case 'remove':
                return self::remove($data, $path);
            case 'replace':
                return self::replace($data, $path, $operation['value']);
            case 'move':
                return self::move($data, $operation['from'], $path);
            case 'copy':
                return self::copy($data, $operation['from'], $path);
            case 'test':
                self::test($data, $path, $operation['value']);
                return $data;
            default:
                throw new Exception("Unsupported operation: {$op}");
        }
    }
    
    private static function add(array $data, string $path, $value): array
    {
        $pathParts = self::parsePath($path);
        
        if (empty($pathParts)) {
            throw new Exception("Invalid path for add operation");
        }
        
        $current = &$data;
        $lastKey = array_pop($pathParts);
        
        // Navigate to parent
        foreach ($pathParts as $part) {
            if (!isset($current[$part])) {
                $current[$part] = [];
            }
            $current = &$current[$part];
        }
        
        // Handle array index notation
        if ($lastKey === '-') {
            if (!is_array($current)) {
                $current = [];
            }
            $current[] = $value;
        } else {
            $current[$lastKey] = $value;
        }
        
        return $data;
    }
    
    private static function remove(array $data, string $path): array
    {
        $pathParts = self::parsePath($path);
        
        if (empty($pathParts)) {
            throw new Exception("Invalid path for remove operation");
        }
        
        $current = &$data;
        $lastKey = array_pop($pathParts);
        
        // Navigate to parent
        foreach ($pathParts as $part) {
            if (!isset($current[$part])) {
                throw new Exception("Path not found: {$path}");
            }
            $current = &$current[$part];
        }
        
        // Remove the value
        if (is_array($current) && array_key_exists($lastKey, $current)) {
            unset($current[$lastKey]);
        } else {
            throw new Exception("Path not found: {$path}");
        }
        
        return $data;
    }
    
    private static function replace(array $data, string $path, $value): array
    {
        $pathParts = self::parsePath($path);
        
        if (empty($pathParts)) {
            throw new Exception("Invalid path for replace operation");
        }
        
        $current = &$data;
        $lastKey = array_pop($pathParts);
        
        // Navigate to parent
        foreach ($pathParts as $part) {
            if (!isset($current[$part])) {
                throw new Exception("Path not found: {$path}");
            }
            $current = &$current[$part];
        }
        
        // Replace the value
        if (is_array($current) && array_key_exists($lastKey, $current)) {
            $current[$lastKey] = $value;
        } else {
            throw new Exception("Path not found: {$path}");
        }
        
        return $data;
    }
    
    private static function move(array $data, string $fromPath, string $toPath): array
    {
        // Get the value to move
        $value = self::getValue($data, $fromPath);
        
        // Remove from source
        $data = self::remove($data, $fromPath);
        
        // Add to destination
        $data = self::add($data, $toPath, $value);
        
        return $data;
    }
    
    private static function copy(array $data, string $fromPath, string $toPath): array
    {
        // Get the value to copy
        $value = self::getValue($data, $fromPath);
        
        // Add to destination
        $data = self::add($data, $toPath, $value);
        
        return $data;
    }
    
    private static function test(array $data, string $path, $expectedValue): void
    {
        $actualValue = self::getValue($data, $path);
        
        if ($actualValue !== $expectedValue) {
            throw new Exception("Test failed: expected " . json_encode($expectedValue) . ", got " . json_encode($actualValue));
        }
    }
    
    private static function getValue(array $data, string $path)
    {
        $pathParts = self::parsePath($path);
        $current = $data;
        
        foreach ($pathParts as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                throw new Exception("Path not found: {$path}");
            }
            $current = $current[$part];
        }
        
        return $current;
    }
    
    private static function parsePath(string $path): array
    {
        if ($path === '') {
            return [];
        }
        
        if (!str_starts_with($path, '/')) {
            throw new Exception("Invalid JSON Pointer: must start with '/'");
        }
        
        $parts = explode('/', substr($path, 1));
        
        // Decode JSON Pointer escape sequences
        return array_map(function($part) {
            return str_replace(['~1', '~0'], ['/', '~'], $part);
        }, $parts);
    }
}