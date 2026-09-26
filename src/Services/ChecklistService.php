<?php

declare(strict_types=1);

namespace FlexiList\Services;

use FlexiList\Models\ChecklistModel;
use FlexiList\Models\FlexibleItem;
use FlexiList\Models\ValidationException;
use Exception;

class ChecklistService
{
    private string $dataDir;

    public function __construct(string $dataDir = 'data')
    {
        $this->dataDir = $dataDir;
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
    }

    public function validateListId(string $listId): string
    {
        if (!preg_match('/^[a-zA-Z0-9]+$/', $listId)) {
            throw new ValidationException('ID must contain only alphanumeric characters');
        }
        if (strlen($listId) > 32) {
            throw new ValidationException('ID must not exceed 32 characters');
        }
        return $listId;
    }

    public function getListFilePath(string $listId): string
    {
        return $this->dataDir . '/' . $listId . '.json';
    }

    public function createEmptyList(): array
    {
        return [
            'Metadata' => [
                'Title' => 'Your Checklist',
                'Hide_Checked' => false
            ],
            'Checklist' => []
        ];
    }

    public function cleanItemData(array $itemDict): array
    {
        $cleaned = [];

        // Always include these fields
        $cleaned['Type'] = $itemDict['Type'];
        $cleaned['Name'] = $itemDict['Name'];
        $cleaned['Order'] = $itemDict['Order'];

        if ($itemDict['Type'] === 'Category') {
            // For categories, include Content and Expanded, exclude item-specific fields
            $cleaned['Content'] = [];
            if (isset($itemDict['Content']) && $itemDict['Content'] !== null) {
                $cleaned['Content'] = array_map([$this, 'cleanItemData'], $itemDict['Content']);
            }
            // Include expansion state, default to true if not specified
            $cleaned['Expanded'] = $itemDict['Expanded'] ?? true;
        } elseif ($itemDict['Type'] === 'Item') {
            // For items, include item-specific fields and exclude Content/Expanded
            $cleaned['Checked'] = $itemDict['Checked'] ?? false;
            $cleaned['Notes'] = $itemDict['Notes'] ?? '';
        }

        return $cleaned;
    }

    public function cleanChecklistData(array $data): array
    {
        $cleaned = [
            'Checklist' => array_map([$this, 'cleanItemData'], $data['Checklist'] ?? [])
        ];
        
        // Preserve Metadata if it exists
        if (isset($data['Metadata']) && is_array($data['Metadata'])) {
            $cleaned['Metadata'] = $data['Metadata'];
        }
        
        return $cleaned;
    }

    public function getChecklist(string $listId): array
    {
        // Validate ID
        $this->validateListId($listId);

        $filePath = $this->getListFilePath($listId);

        // If file doesn't exist, create empty list
        if (!file_exists($filePath)) {
            $emptyList = $this->createEmptyList();
            try {
                file_put_contents($filePath, json_encode($emptyList, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } catch (Exception $e) {
                throw new Exception("Could not create list: " . $e->getMessage());
            }
            return $emptyList;
        }

        // Read existing file
        try {
            $content = file_get_contents($filePath);
            $data = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Corrupted JSON file');
            }

            // Validate structure
            ChecklistModel::validate($data);
            return $data;

        } catch (Exception $e) {
            throw new Exception("Could not read list: " . $e->getMessage());
        }
    }

    public function updateChecklist(string $listId, ChecklistModel $checklist): array
    {
        // Validate ID
        $this->validateListId($listId);

        $filePath = $this->getListFilePath($listId);

        try {
            // Convert to array and clean up the data structure
            $rawData = $checklist->toArray();
            $cleanedData = $this->cleanChecklistData($rawData);

            // Debug logging - print the data structure being saved
            error_log("Saving list {$listId} with cleaned data structure:");
            error_log(json_encode($cleanedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            file_put_contents($filePath, json_encode($cleanedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // Verify the saved data by reading it back
            $savedData = json_decode(file_get_contents($filePath), true);

            error_log("Verified saved data for {$listId}:");
            error_log(json_encode($savedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return ['message' => 'List updated successfully', 'id' => $listId];

        } catch (Exception $e) {
            error_log("Error saving list {$listId}: " . $e->getMessage());
            throw new Exception("Could not save list: " . $e->getMessage());
        }
    }

    public function getRootLevelData(string $listId): array
    {
        $checklistData = $this->getChecklist($listId);
        $items = $checklistData['Checklist'] ?? [];

        $checklistItems = [];
        $categories = [];

        foreach ($items as $item) {
            if (($item['Type'] ?? '') === 'Item') {
                $checklistItems[] = $item;
            } elseif (($item['Type'] ?? '') === 'Category') {
                $categories[] = $item;
            }
        }

        return [
            'items' => $checklistItems,
            'categories' => $categories
        ];
    }

    public function findCategoryByName(array $items, string $categoryName): ?array
    {
        foreach ($items as $item) {
            if (($item['Type'] ?? '') === 'Category' && ($item['Name'] ?? '') === $categoryName) {
                return $item;
            }
            if (($item['Type'] ?? '') === 'Category' && !empty($item['Content'])) {
                $found = $this->findCategoryByName($item['Content'], $categoryName);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        return null;
    }

    public function moveItemToCategory(string $listId, string $itemName, ?string $categoryName): bool
    {
        $checklistData = $this->getChecklist($listId);
        $items = &$checklistData['Checklist'];

        // Find the item in root level
        $itemIndex = null;
        $item = null;
        foreach ($items as $i => $listItem) {
            if (($listItem['Type'] ?? '') === 'Item' && ($listItem['Name'] ?? '') === $itemName) {
                $itemIndex = $i;
                $item = $listItem;
                break;
            }
        }

        if ($itemIndex === null) {
            error_log("Item '{$itemName}' not found at root level");
            return false;
        }

        // If categoryName is null, keep item at root level
        if ($categoryName === null) {
            return true;
        }

        // Find the target category
        $targetCategory = $this->findCategoryByName($items, $categoryName);
        if (!$targetCategory) {
            error_log("Category '{$categoryName}' not found");
            return false;
        }

        // Move the item
        array_splice($items, $itemIndex, 1);

        // Ensure target category has Content array
        if (!isset($targetCategory['Content'])) {
            $targetCategory['Content'] = [];
        }

        // Auto-expand target category
        $targetCategory['Expanded'] = true;

        // Add item to target category
        $targetCategory['Content'][] = $item;

        // Reorder both lists
        $this->reorderItems($items);
        $this->reorderItems($targetCategory['Content']);

        // Save the updated list
        $checklist = new ChecklistModel($checklistData);
        $this->updateChecklist($listId, $checklist);

        return true;
    }

    private function reorderItems(array &$items): void
    {
        foreach ($items as $idx => $item) {
            $items[$idx]['Order'] = $idx + 1;
        }

        // Sort items by Order
        usort($items, fn($a, $b) => ($a['Order'] ?? 0) <=> ($b['Order'] ?? 0));
    }

    public function applyAutoCategorization(string $listId, array $assignments): bool
    {
        try {
            foreach ($assignments as $assignment) {
                $itemName = $assignment['item'] ?? null;
                $categoryName = $assignment['category'] ?? null;

                if ($itemName) {
                    $success = $this->moveItemToCategory($listId, $itemName, $categoryName);
                    if (!$success) {
                        error_log("Failed to move item '{$itemName}' to category '{$categoryName}'");
                    }
                }
            }

            return true;
        } catch (Exception $e) {
            error_log("Error applying auto-categorization: " . $e->getMessage());
            return false;
        }
    }

    public function listExists(string $listId): bool
    {
        try {
            $this->validateListId($listId);
            $filePath = $this->getListFilePath($listId);
            return file_exists($filePath);
        } catch (Exception $e) {
            return false;
        }
    }

    public function deleteList(string $listId): bool
    {
        try {
            $this->validateListId($listId);
            $filePath = $this->getListFilePath($listId);
            if (file_exists($filePath)) {
                unlink($filePath);
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("Error deleting list {$listId}: " . $e->getMessage());
            return false;
        }
    }

    public function getListStats(string $listId): array
    {
        try {
            $checklistData = $this->getChecklist($listId);
            $items = $checklistData['Checklist'] ?? [];

            $stats = [
                'total_items' => 0,
                'items' => 0,
                'categories' => 0,
                'checked_items' => 0,
                'unchecked_items' => 0
            ];

            $countItems = function (array $itemList) use (&$stats, &$countItems) {
                foreach ($itemList as $item) {
                    $stats['total_items']++;

                    if (($item['Type'] ?? '') === 'Item') {
                        $stats['items']++;
                        if ($item['Checked'] ?? false) {
                            $stats['checked_items']++;
                        } else {
                            $stats['unchecked_items']++;
                        }
                    } elseif (($item['Type'] ?? '') === 'Category') {
                        $stats['categories']++;
                        if (!empty($item['Content'])) {
                            $countItems($item['Content']);
                        }
                    }
                }
            };

            $countItems($items);
            return $stats;

        } catch (Exception $e) {
            error_log("Error getting stats for list {$listId}: " . $e->getMessage());
            return [];
        }
    }
}