<?php

declare(strict_types=1);

namespace FlexiList\Models;

class PatchMetrics
{
    public array $operationCounts;
    public int $bandwidthSaved;
    public int $conflictCount;
    public int $errorCount;

    public function __construct()
    {
        $this->operationCounts = [];
        $this->bandwidthSaved = 0;
        $this->conflictCount = 0;
        $this->errorCount = 0;
    }

    public function recordPatch(array $operations, int $originalSize, int $patchSize): void
    {
        foreach ($operations as $op) {
            $opType = $op['op'] ?? 'unknown';
            $this->operationCounts[$opType] = ($this->operationCounts[$opType] ?? 0) + 1;
        }
        $this->bandwidthSaved += max(0, $originalSize - $patchSize);
    }

    public function recordConflict(): void
    {
        $this->conflictCount++;
    }

    public function recordError(): void
    {
        $this->errorCount++;
    }

    public function getSummary(): array
    {
        return [
            'operations' => $this->operationCounts,
            'bandwidth_saved_bytes' => $this->bandwidthSaved,
            'conflicts' => $this->conflictCount,
            'errors' => $this->errorCount
        ];
    }
}