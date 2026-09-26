<?php

declare(strict_types=1);

namespace FlexiList\Controllers;

use FlexiList\Services\ChecklistService;
use FlexiList\Services\OpenRouterService;
use FlexiList\Services\PatchService;
use FlexiList\Services\ConsistencyService;
use FlexiList\Models\ChecklistModel;
use FlexiList\Models\CategorizationRequest;
use FlexiList\Models\JsonPatchRequest;
use FlexiList\Models\ListVersion;
use FlexiList\Models\PatchMetrics;
use FlexiList\Models\ValidationException;
use Exception;
use NativeRequest;
use NativeResponse;

class ChecklistController
{
    private ChecklistService $checklistService;
    private OpenRouterService $openRouterService;
    private PatchMetrics $patchMetrics;

    public function __construct(
        ChecklistService $checklistService,
        OpenRouterService $openRouterService,
        PatchMetrics $patchMetrics
    ) {
        $this->checklistService = $checklistService;
        $this->openRouterService = $openRouterService;
        $this->patchMetrics = $patchMetrics;
    }

    public function getChecklist(NativeRequest $request): NativeResponse
    {
        $listId = $request->getPathParam('list_key');

        try {
            $data = $this->checklistService->getChecklist($listId);
            return (new NativeResponse())
                ->write(json_encode($data, JSON_UNESCAPED_UNICODE))
                ->withHeader('Content-Type', 'application/json');
        } catch (ValidationException $e) {
            $error = ['error' => 'Validation Error', 'detail' => $e->getMessage()];
            return (new NativeResponse())
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json')
                ->write(json_encode($error, JSON_UNESCAPED_UNICODE));
        } catch (Exception $e) {
            $error = ['error' => 'Internal Server Error', 'detail' => $e->getMessage()];
            return (new NativeResponse())
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->write(json_encode($error, JSON_UNESCAPED_UNICODE));
        }
    }

    public function updateChecklist(NativeRequest $request): NativeResponse
    {
        $listId = $request->getPathParam('list_key');

        try {
            $data = json_decode($request->getBody(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ValidationException('Invalid JSON');
            }

            $checklist = new ChecklistModel($data);
            $result = $this->checklistService->updateChecklist($listId, $checklist);

            return (new NativeResponse())
                ->write(json_encode($result, JSON_UNESCAPED_UNICODE))
                ->withHeader('Content-Type', 'application/json');
        } catch (ValidationException $e) {
            $error = ['error' => 'Validation Error', 'detail' => $e->getMessage()];
            return (new NativeResponse())
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json')
                ->write(json_encode($error, JSON_UNESCAPED_UNICODE));
        } catch (Exception $e) {
            $error = ['error' => 'Internal Server Error', 'detail' => $e->getMessage()];
            return (new NativeResponse())
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->write(json_encode($error, JSON_UNESCAPED_UNICODE));
        }
    }

    public function patchChecklist(NativeRequest $request): NativeResponse
    {
        $listId = $request->getPathParam('list_key');

        try {
            // Check if list exists first (don't auto-create for PATCH)
            if (!$this->checklistService->listExists($listId)) {
                $error = ['error' => 'Not Found', 'detail' => 'Checklist not found'];
                return (new NativeResponse())
                    ->withStatus(404)
                    ->withHeader('Content-Type', 'application/json')
                    ->write(json_encode($error, JSON_UNESCAPED_UNICODE));
            }

            // Parse patch request
            $data = json_decode($request->getBody(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ValidationException('Invalid JSON');
            }

            $patchRequest = new JsonPatchRequest($data);

            // Get current data
            $currentData = $this->checklistService->getChecklist($listId);
            $currentVersion = new ListVersion($currentData);

            // Check If-Match header for conflict detection
            $ifMatch = $request->getHeaderLine('If-Match');
            if (!empty($ifMatch) && $ifMatch !== $currentVersion->etag) {
                $this->patchMetrics->recordConflict();
                $conflictResponse = [
                    'error' => 'Conflict',
                    'detail' => 'List was modified by another user',
                    'current_etag' => $currentVersion->etag,
                    'current_data' => $currentData
                ];
                return (new NativeResponse())
                    ->withStatus(409)
                    ->withHeader('Content-Type', 'application/json')
                    ->withHeader('Retry-After', '1')
                    ->write(json_encode($conflictResponse, JSON_UNESCAPED_UNICODE));
            }

            // Validate patch operations with detailed error reporting
            [$isValid, $validationErrors] = PatchService::validatePatchWithDetails($currentData, $patchRequest->operations);
            if (!$isValid) {
                $this->patchMetrics->recordError();
                $error = [
                    'error' => 'Bad Request',
                    'detail' => 'Invalid patch operations: ' . implode('; ', $validationErrors)
                ];
                return (new NativeResponse())
                    ->withStatus(400)
                    ->withHeader('Content-Type', 'application/json')
                    ->write(json_encode($error, JSON_UNESCAPED_UNICODE));
            }

            // Calculate sizes for metrics
            $originalSize = strlen(json_encode($currentData, JSON_UNESCAPED_UNICODE));
            $patchSize = PatchService::calculatePatchSize($patchRequest->operations);

            // Apply patch with fallback support
            try {
                // Validate data consistency before applying patch
                $consistencyReport = ConsistencyService::validateListIntegrity($currentData);
                if (!$consistencyReport->isValid) {
                    error_log("Data consistency issues detected for list {$listId}: " . implode(', ', $consistencyReport->errors));
                    // Attempt to repair data
                    [$repairedData, $repairReport] = ConsistencyService::repairDataIfPossible($currentData);
                    if ($repairReport->repaired) {
                        error_log("Data repaired for list {$listId}");
                        $currentData = $repairedData;
                    }
                }

                // Apply patch
                $updatedData = PatchService::applyPatchWithFallback(
                    $currentData,
                    $patchRequest->operations,
                    $currentData  // Use current data as fallback
                );

                // Validate result using existing model
                $validatedList = new ChecklistModel($updatedData);

                // Final consistency check
                $finalConsistency = ConsistencyService::validateListIntegrity($updatedData);
                if (!$finalConsistency->isValid) {
                    error_log("Final data consistency check failed for list {$listId}: " . implode(', ', $finalConsistency->errors));
                    $this->patchMetrics->recordError();
                    $error = ['error' => 'Internal Server Error', 'detail' => 'Data consistency validation failed after patch'];
                    return (new NativeResponse())
                        ->withStatus(500)
                        ->withHeader('Content-Type', 'application/json')
                        ->write(json_encode($error, JSON_UNESCAPED_UNICODE));
                }

                // Save updated data
                $this->checklistService->updateChecklist($listId, $validatedList);

                // Create new version for response
                $newVersion = new ListVersion($updatedData);

                // Record metrics
                $operationsArray = array_map(fn($op) => $op->toArray(), $patchRequest->operations);
                $this->patchMetrics->recordPatch($operationsArray, $originalSize, $patchSize);

                // Log patch summary for monitoring
                $summary = PatchService::getPatchSummary($patchRequest->operations);
                error_log("Applied patch to list {$listId}: " . json_encode($summary));
                error_log("Bandwidth saved: " . ($originalSize - $patchSize) . " bytes");

                // Return the updated data with new ETag
                return (new NativeResponse())
                    ->withStatus(200)
                    ->withHeader('Content-Type', 'application/json')
                    ->withHeader('ETag', $newVersion->etag)
                    ->write(json_encode($updatedData, JSON_UNESCAPED_UNICODE));

            } catch (Exception $e) {
                $this->patchMetrics->recordError();
                error_log("Patch application failed for list {$listId}: " . $e->getMessage());
                $error = ['error' => 'Bad Request', 'detail' => 'Patch application failed: ' . $e->getMessage()];
                return (new NativeResponse())
                    ->withStatus(400)
                    ->withHeader('Content-Type', 'application/json')
                    ->write(json_encode($error, JSON_UNESCAPED_UNICODE));
            }

        } catch (ValidationException $e) {
            $this->patchMetrics->recordError();
            $error = ['error' => 'Validation Error', 'detail' => $e->getMessage()];
            return (new NativeResponse())
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json')
                ->write(json_encode($error, JSON_UNESCAPED_UNICODE));
        } catch (Exception $e) {
            $this->patchMetrics->recordError();
            $error = ['error' => 'Internal Server Error', 'detail' => $e->getMessage()];
            return (new NativeResponse())
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->write(json_encode($error, JSON_UNESCAPED_UNICODE));
        }
    }

    public function autoCategorizeItems(NativeRequest $request): NativeResponse
    {
        $listId = $request->getPathParam('list_key');

        try {
            // Validate ID
            $this->checklistService->validateListId($listId);

            // Parse request
            $data = json_decode($request->getBody(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ValidationException('Invalid JSON');
            }

            $categorizationRequest = new CategorizationRequest($data);

            // Edge case validation
            if (empty($categorizationRequest->items)) {
                $error = ['error' => 'Bad Request', 'detail' => 'No items found to categorize'];
                return (new NativeResponse())
                    ->withStatus(400)
                    ->withHeader('Content-Type', 'application/json')
                    ->write(json_encode($error, JSON_UNESCAPED_UNICODE));
            }

            if (empty($categorizationRequest->categories)) {
                $error = ['error' => 'Bad Request', 'detail' => 'No categories available for assignment'];
                return (new NativeResponse())
                    ->withStatus(400)
                    ->withHeader('Content-Type', 'application/json')
                    ->write(json_encode($error, JSON_UNESCAPED_UNICODE));
            }

            // Get list title for context
            $listData = $this->checklistService->getChecklist($listId);
            $listTitle = $listData['Metadata']['Title'] ?? '';

            // Call OpenRouter service
            $result = $this->openRouterService->categorizeItems(
                $categorizationRequest->items,
                $categorizationRequest->categories,
                $listTitle
            );

            error_log("Auto-categorization for list {$listId}:");
            error_log("List Title: " . $listTitle);
            error_log("Items: " . json_encode($categorizationRequest->items));
            error_log("Categories: " . json_encode($categorizationRequest->categories));
            error_log("Assignments: " . json_encode($result->toArray()['assignments']));

            return (new NativeResponse())
                ->write(json_encode($result->toArray(), JSON_UNESCAPED_UNICODE))
                ->withHeader('Content-Type', 'application/json');

        } catch (ValidationException $e) {
            $error = ['error' => 'Validation Error', 'detail' => $e->getMessage()];
            return (new NativeResponse())
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json')
                ->write(json_encode($error, JSON_UNESCAPED_UNICODE));
        } catch (Exception $e) {
            error_log("Unexpected error in auto-categorization: " . $e->getMessage());
            
            // Check for specific OpenRouter errors
            if (strpos($e->getMessage(), 'OpenRouter API key not configured') !== false) {
                $status = 500;
                $detail = 'OpenRouter API key not configured';
            } elseif (strpos($e->getMessage(), 'Request too long') !== false) {
                $status = 400;
                $detail = 'Request too long for categorization';
            } elseif (strpos($e->getMessage(), 'OpenRouter API error') !== false) {
                $status = 503;
                $detail = 'OpenRouter API temporarily unavailable';
            } elseif (strpos($e->getMessage(), 'not available') !== false) {
                $status = 503;
                $detail = 'Categorization service temporarily unavailable';
            } else {
                $status = 500;
                $detail = 'Categorization service error: ' . $e->getMessage();
            }

            $error = [
                'success' => false,
                'error' => 'Categorization Failed', 
                'detail' => $detail,
                'message' => 'Auto-categorization is temporarily unavailable. Please try again later or categorize items manually.'
            ];
            
            return (new NativeResponse())
                ->withStatus($status)
                ->withHeader('Content-Type', 'application/json')
                ->write(json_encode($error, JSON_UNESCAPED_UNICODE));
        }
    }

    public function getListStats(NativeRequest $request): NativeResponse
    {
        $listId = $request->getPathParam('list_key');

        try {
            $stats = $this->checklistService->getListStats($listId);
            return (new NativeResponse())
                ->write(json_encode($stats, JSON_UNESCAPED_UNICODE))
                ->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            $error = ['error' => 'Internal Server Error', 'detail' => $e->getMessage()];
            return (new NativeResponse())
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->write(json_encode($error, JSON_UNESCAPED_UNICODE));
        }
    }

    public function deleteChecklist(NativeRequest $request): NativeResponse
    {
        $listId = $request->getPathParam('list_key');

        try {
            $success = $this->checklistService->deleteList($listId);
            if ($success) {
                $result = ['message' => 'List deleted successfully', 'id' => $listId];
                return (new NativeResponse())
                    ->write(json_encode($result, JSON_UNESCAPED_UNICODE))
                    ->withHeader('Content-Type', 'application/json');
            } else {
                $error = ['error' => 'Not Found', 'detail' => 'List not found'];
                return (new NativeResponse())
                    ->withStatus(404)
                    ->withHeader('Content-Type', 'application/json')
                    ->write(json_encode($error, JSON_UNESCAPED_UNICODE));
            }
        } catch (Exception $e) {
            $error = ['error' => 'Internal Server Error', 'detail' => $e->getMessage()];
            return (new NativeResponse())
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->write(json_encode($error, JSON_UNESCAPED_UNICODE));
        }
    }

    public function checkListExists(NativeRequest $request): NativeResponse
    {
        $listId = $request->getPathParam('list_key');

        try {
            $exists = $this->checklistService->listExists($listId);
            $result = ['exists' => $exists, 'id' => $listId];
            return (new NativeResponse())
                ->write(json_encode($result, JSON_UNESCAPED_UNICODE))
                ->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            $error = ['error' => 'Internal Server Error', 'detail' => $e->getMessage()];
            return (new NativeResponse())
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->write(json_encode($error, JSON_UNESCAPED_UNICODE));
        }
    }

    public function getPatchMetrics(NativeRequest $request): NativeResponse
    {
        try {
            $metrics = $this->patchMetrics->getSummary();
            return (new NativeResponse())
                ->write(json_encode($metrics, JSON_UNESCAPED_UNICODE))
                ->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            $error = ['error' => 'Internal Server Error', 'detail' => $e->getMessage()];
            return (new NativeResponse())
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->write(json_encode($error, JSON_UNESCAPED_UNICODE));
        }
    }
}