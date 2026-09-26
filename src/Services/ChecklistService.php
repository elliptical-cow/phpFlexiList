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
            mkdir($this->dataDir, 0700, true);
        }
    }

    public function validateListId(string $listId): string
    {
        if (!preg_match('/^[a-f0-9]{24}$/', $listId)) {
            throw new ValidationException('Invalid list identifier');
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

        if (!file_exists($filePath)) {
            throw new ValidationException('Checklist not found');
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

    public function createChecklist(string $listId): array
    {
        $this->validateListId($listId);
        $filePath = $this->getListFilePath($listId);
        if (file_exists($filePath)) {
            throw new ValidationException('Checklist already exists');
        }

        $list = $this->createEmptyList();
        $this->writeJsonAtomically($filePath, $list);
        return $list;
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

            $this->writeJsonAtomically($filePath, $cleanedData);

            return ['message' => 'List updated successfully', 'id' => $listId];

        } catch (Exception $e) {
            error_log("Error saving list {$listId}: " . $e->getMessage());
            throw new Exception("Could not save list: " . $e->getMessage());
        }
    }

    private function writeJsonAtomically(string $filePath, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $temporaryPath = $filePath . '.tmp.' . bin2hex(random_bytes(6));

        if (file_put_contents($temporaryPath, $json, LOCK_EX) === false) {
            throw new Exception('Could not write temporary list file');
        }

        if (!rename($temporaryPath, $filePath)) {
            unlink($temporaryPath);
            throw new Exception('Could not replace list file');
        }
        chmod($filePath, 0600);
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
            return false;
        }

        // If categoryName is null, keep item at root level
        if ($categoryName === null) {
            return true;
        }

        // Find the target category
        $targetCategory = $this->findCategoryByName($items, $categoryName);
        if (!$targetCategory) {
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
                    // Continue processing independent assignments after a miss.
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
