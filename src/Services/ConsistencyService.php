<?php

declare(strict_types=1);

namespace FlexiList\Services;

use FlexiList\Models\ChecklistModel;
use FlexiList\Models\ConsistencyReport;
use Exception;

class ConsistencyService
{
    public static function validateListIntegrity(array $data): ConsistencyReport
    {
        $errors = [];
        $warnings = [];

        // Check required structure
        if (!isset($data['Checklist'])) {
            $errors[] = "Missing 'Checklist' key in data structure";
            return new ConsistencyReport(false, $errors, $warnings);
        }

        if (!is_array($data['Checklist'])) {
            $errors[] = "'Checklist' must be a list";
            return new ConsistencyReport(false, $errors, $warnings);
        }

        // Validate each item
        foreach ($data['Checklist'] as $i => $item) {
            [$itemErrors, $itemWarnings] = self::validateItem($item, "Item {$i}");
            $errors = array_merge($errors, $itemErrors);
            $warnings = array_merge($warnings, $itemWarnings);
        }

        // Check for duplicate Order values
        $orderValues = [];
        foreach ($data['Checklist'] as $i => $item) {
            if (is_array($item) && isset($item['Order'])) {
                $order = $item['Order'];
                if (in_array($order, $orderValues)) {
                    $warnings[] = "Duplicate Order value {$order} found";
                }
                $orderValues[] = $order;
            }
        }

        return new ConsistencyReport(
            empty($errors),
            $errors,
            $warnings
        );
    }

    private static function validateItem($item, string $context): array
    {
        $errors = [];
        $warnings = [];

        if (!is_array($item)) {
            $errors[] = "{$context}: Item must be a dictionary";
            return [$errors, $warnings];
        }

        // Check required fields
        $requiredFields = ['Type', 'Name', 'Order'];
        foreach ($requiredFields as $field) {
            if (!isset($item[$field])) {
                $errors[] = "{$context}: Missing required field '{$field}'";
            }
        }

        // Validate Type field
        if (isset($item['Type'])) {
            if (!in_array($item['Type'], ['Item', 'Category'])) {
                $errors[] = "{$context}: Invalid Type '{$item['Type']}', must be 'Item' or 'Category'";
            }
        }

        // Validate Name field
        if (isset($item['Name'])) {
            if (!is_string($item['Name']) || trim($item['Name']) === '') {
                $errors[] = "{$context}: Name must be a non-empty string";
            }
        }

        // Validate Order field
        if (isset($item['Order'])) {
            if (!is_int($item['Order']) || $item['Order'] < 1) {
                $errors[] = "{$context}: Order must be a positive integer";
            }
        }

        // Type-specific validation
        if (isset($item['Type'])) {
            if ($item['Type'] === 'Item') {
                [$itemErrors, $itemWarnings] = self::validateChecklistItem($item, $context);
                $errors = array_merge($errors, $itemErrors);
                $warnings = array_merge($warnings, $itemWarnings);
            } elseif ($item['Type'] === 'Category') {
                [$categoryErrors, $categoryWarnings] = self::validateCategory($item, $context);
                $errors = array_merge($errors, $categoryErrors);
                $warnings = array_merge($warnings, $categoryWarnings);
            }
        }

        return [$errors, $warnings];
    }

    private static function validateChecklistItem(array $item, string $context): array
    {
        $errors = [];
        $warnings = [];

        // Check that item doesn't have category fields
        if (isset($item['Content'])) {
            $warnings[] = "{$context}: Item should not have 'Content' field";
        }
        if (isset($item['Expanded'])) {
            $warnings[] = "{$context}: Item should not have 'Expanded' field";
        }

        // Validate item-specific fields
        if (isset($item['Checked']) && !is_bool($item['Checked'])) {
            $errors[] = "{$context}: Checked must be a boolean";
        }

        if (isset($item['Notes']) && !is_string($item['Notes'])) {
            $errors[] = "{$context}: Notes must be a string";
        }

        return [$errors, $warnings];
    }

    private static function validateCategory(array $item, string $context): array
    {
        $errors = [];
        $warnings = [];

        // Check that category doesn't have item fields
        $itemFields = ['Checked', 'Notes'];
        foreach ($itemFields as $field) {
            if (isset($item[$field])) {
                $warnings[] = "{$context}: Category should not have '{$field}' field";
            }
        }

        // Validate category-specific fields
        if (isset($item['Expanded']) && !is_bool($item['Expanded'])) {
            $errors[] = "{$context}: Expanded must be a boolean";
        }

        // Validate Content field
        if (isset($item['Content'])) {
            if (!is_array($item['Content'])) {
                $errors[] = "{$context}: Content must be a list";
            } else {
                // Recursively validate nested items
                foreach ($item['Content'] as $j => $nestedItem) {
                    [$nestedErrors, $nestedWarnings] = self::validateItem(
                        $nestedItem, 
                        "{$context}.Content[{$j}]"
                    );
                    $errors = array_merge($errors, $nestedErrors);
                    $warnings = array_merge($warnings, $nestedWarnings);
                }
            }
        }

        return [$errors, $warnings];
    }

    public static function repairDataIfPossible(array $data): array
    {
        $repaired = json_decode(json_encode($data), true); // Deep copy
        $repairsMade = [];
        $warnings = [];

        // Ensure Checklist exists
        if (!isset($repaired['Checklist'])) {
            $repaired['Checklist'] = [];
            $repairsMade[] = "Added missing 'Checklist' key";
        }

        // Ensure Checklist is a list
        if (!is_array($repaired['Checklist'])) {
            $repaired['Checklist'] = [];
            $repairsMade[] = "Converted 'Checklist' to list";
        }

        // Fix items in the list
        $itemsToRemove = [];
        foreach ($repaired['Checklist'] as $i => $item) {
            if (!is_array($item)) {
                // Mark invalid items for removal
                $itemsToRemove[] = $i;
                $repairsMade[] = "Marked invalid item at index {$i} for removal";
                continue;
            }

            // Fix missing required fields
            if (!isset($item['Type'])) {
                $repaired['Checklist'][$i]['Type'] = 'Item'; // Default to item
                $repairsMade[] = "Added missing 'Type' field to item {$i}";
            }

            if (!isset($item['Name']) || !is_string($item['Name']) || trim($item['Name']) === '') {
                $repaired['Checklist'][$i]['Name'] = "Item " . ($i + 1);
                $repairsMade[] = "Fixed missing/invalid 'Name' field for item {$i}";
            }

            if (!isset($item['Order']) || !is_int($item['Order'])) {
                $repaired['Checklist'][$i]['Order'] = $i + 1;
                $repairsMade[] = "Fixed missing/invalid 'Order' field for item {$i}";
            }

            // Fix type-specific fields
            if ($repaired['Checklist'][$i]['Type'] === 'Item') {
                $repairsMade = array_merge($repairsMade, self::repairChecklistItem($repaired['Checklist'][$i], $i));
            } elseif ($repaired['Checklist'][$i]['Type'] === 'Category') {
                $repairsMade = array_merge($repairsMade, self::repairCategory($repaired['Checklist'][$i], $i));
            }
        }

        // Remove invalid items (in reverse order to maintain indices)
        $originalLength = count($repaired['Checklist']);
        foreach (array_reverse($itemsToRemove) as $index) {
            array_splice($repaired['Checklist'], $index, 1);
        }
        if (count($repaired['Checklist']) < $originalLength) {
            $removed = $originalLength - count($repaired['Checklist']);
            $repairsMade[] = "Removed {$removed} invalid items";
        }

        // Fix Order ordering
        foreach ($repaired['Checklist'] as $i => $item) {
            if ($item['Order'] !== $i + 1) {
                $repaired['Checklist'][$i]['Order'] = $i + 1;
                $repairsMade[] = "Fixed Order ordering for item {$i}";
            }
        }

        $report = new ConsistencyReport(
            true, // After repair, assume it's valid
            [],
            $warnings,
            !empty($repairsMade)
        );

        if (!empty($repairsMade)) {
            error_log("Data repairs made: " . implode(', ', $repairsMade));
        }

        return [$repaired, $report];
    }

    private static function repairChecklistItem(array &$item, int $index): array
    {
        $repairsMade = [];

        // Remove category fields
        if (isset($item['Content'])) {
            unset($item['Content']);
            $repairsMade[] = "Removed 'Content' field from item {$index}";
        }
        if (isset($item['Expanded'])) {
            unset($item['Expanded']);
            $repairsMade[] = "Removed 'Expanded' field from item {$index}";
        }

        // Add missing item fields with defaults
        if (!isset($item['Checked'])) {
            $item['Checked'] = false;
            $repairsMade[] = "Added missing 'Checked' field to item {$index}";
        } elseif (!is_bool($item['Checked'])) {
            $item['Checked'] = (bool)$item['Checked'];
            $repairsMade[] = "Fixed 'Checked' field type for item {$index}";
        }


        if (!isset($item['Notes'])) {
            $item['Notes'] = '';
            $repairsMade[] = "Added missing 'Notes' field to item {$index}";
        } elseif (!is_string($item['Notes'])) {
            $item['Notes'] = (string)$item['Notes'];
            $repairsMade[] = "Fixed 'Notes' field type for item {$index}";
        }

        return $repairsMade;
    }

    private static function repairCategory(array &$item, int $index): array
    {
        $repairsMade = [];

        // Remove item fields
        $itemFields = ['Checked', 'Notes'];
        foreach ($itemFields as $field) {
            if (isset($item[$field])) {
                unset($item[$field]);
                $repairsMade[] = "Removed '{$field}' field from category {$index}";
            }
        }

        // Add missing category fields with defaults
        if (!isset($item['Content'])) {
            $item['Content'] = [];
            $repairsMade[] = "Added missing 'Content' field to category {$index}";
        } elseif (!is_array($item['Content'])) {
            $item['Content'] = [];
            $repairsMade[] = "Fixed 'Content' field type for category {$index}";
        }

        if (!isset($item['Expanded'])) {
            $item['Expanded'] = true;
            $repairsMade[] = "Added missing 'Expanded' field to category {$index}";
        } elseif (!is_bool($item['Expanded'])) {
            $item['Expanded'] = (bool)$item['Expanded'];
            $repairsMade[] = "Fixed 'Expanded' field type for category {$index}";
        }

        return $repairsMade;
    }

    public static function validateWithPydantic(array $data): array
    {
        try {
            new ChecklistModel($data);
            return [true, 'Data is valid'];
        } catch (Exception $e) {
            return [false, $e->getMessage()];
        }
    }
}