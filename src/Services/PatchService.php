<?php

declare(strict_types=1);

namespace FlexiList\Services;

use FlexiList\Utils\JsonPatch;
use FlexiList\Models\JsonPatchOperation;
use FlexiList\Models\RetryablePatchError;
use Exception;

class PatchService
{
    public static function applyPatch(array $data, array $operations): array
    {
        // Create a deep copy to avoid modifying the original
        $dataCopy = json_decode(json_encode($data), true);

        // Convert operations to the format expected by raso/json-patch
        $patchOps = [];
        foreach ($operations as $op) {
            $patchOp = ['op' => $op->op, 'path' => $op->path];

            if ($op->value !== null) {
                $patchOp['value'] = $op->value;
            }
            if ($op->from !== null) {
                $patchOp['from'] = $op->from;
            }

            $patchOps[] = $patchOp;
        }

        try {
            // Apply patch using custom JsonPatch implementation
            $result = JsonPatch::apply($dataCopy, $patchOps);
            return $result;
        } catch (Exception $e) {
            throw new Exception("Patch application failed: " . $e->getMessage());
        }
    }

    public static function validatePatch(array $data, array $operations): bool
    {
        try {
            self::applyPatch($data, $operations);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function validateOperation(JsonPatchOperation $operation): bool
    {
        $validOps = ['add', 'remove', 'replace', 'move', 'copy', 'test'];

        if (!in_array($operation->op, $validOps)) {
            return false;
        }

        // Path is required for all operations
        if (empty($operation->path) || !str_starts_with($operation->path, '/')) {
            return false;
        }

        // Value is required for add, replace, and test
        if (in_array($operation->op, ['add', 'replace', 'test']) && $operation->value === null) {
            return false;
        }

        // From is required for move and copy
        if (in_array($operation->op, ['move', 'copy']) && empty($operation->from)) {
            return false;
        }

        return true;
    }

    public static function getPatchSummary(array $operations): array
    {
        $summary = [];
        foreach ($operations as $op) {
            $opType = $op->op;
            $summary[$opType] = ($summary[$opType] ?? 0) + 1;
        }
        return $summary;
    }

    public static function applyPatchWithFallback(
        array $data, 
        array $operations, 
        ?array $fallbackData = null
    ): array {
        try {
            return self::applyPatch($data, $operations);
        } catch (Exception $e) {
            error_log("Patch application failed: " . $e->getMessage());

            if ($fallbackData !== null) {
                error_log("Using fallback data due to patch failure");
                return $fallbackData;
            }

            // Determine if this is a retryable error
            if (strpos($e->getMessage(), 'conflict') !== false || strpos($e->getMessage(), 'not found') !== false) {
                throw new RetryablePatchError("Patch conflict: " . $e->getMessage(), 1);
            } else {
                throw new RetryablePatchError("Patch failed: " . $e->getMessage(), 0);
            }
        }
    }

    public static function validatePatchWithDetails(array $data, array $operations): array
    {
        $errors = [];

        // Validate individual operations first
        foreach ($operations as $i => $operation) {
            if (!self::validateOperation($operation)) {
                $errors[] = "Operation {$i}: Invalid operation format";
                continue;
            }
        }

        if (!empty($errors)) {
            return [false, $errors];
        }

        // Try to apply the patch to catch runtime errors
        try {
            self::applyPatch($data, $operations);
            return [true, []];
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'patch') !== false) {
                $errors[] = "JSON Patch error: " . $e->getMessage();
            } elseif (strpos($e->getMessage(), 'not found') !== false) {
                $errors[] = "Path not found: " . $e->getMessage();
            } elseif (strpos($e->getMessage(), 'out of bounds') !== false) {
                $errors[] = "Array index out of bounds: " . $e->getMessage();
            } elseif (strpos($e->getMessage(), 'type') !== false || strpos($e->getMessage(), 'value') !== false) {
                $errors[] = "Type/Value error: " . $e->getMessage();
            } else {
                $errors[] = "Unexpected error: " . $e->getMessage();
            }
        }

        return [false, $errors];
    }

    public static function calculatePatchSize(array $operations): int
    {
        $patchOps = [];
        foreach ($operations as $op) {
            $patchOp = ['op' => $op->op, 'path' => $op->path];

            if ($op->value !== null) {
                $patchOp['value'] = $op->value;
            }
            if ($op->from !== null) {
                $patchOp['from'] = $op->from;
            }

            $patchOps[] = $patchOp;
        }

        // Convert to JSON and calculate size
        $jsonStr = json_encode(['operations' => $patchOps], JSON_UNESCAPED_UNICODE);
        return strlen($jsonStr);
    }

    public static function optimizeOperations(array $operations): array
    {
        // Simple optimization: remove duplicate operations on the same path
        $seenPaths = [];
        $optimized = [];

        foreach ($operations as $op) {
            $pathKey = $op->op . ':' . $op->path;

            // For replace operations, keep only the last one for each path
            if ($op->op === 'replace') {
                $seenPaths[$op->path] = count($optimized);
                $optimized[] = $op;
            } else {
                $optimized[] = $op;
            }
        }

        return $optimized;
    }
}