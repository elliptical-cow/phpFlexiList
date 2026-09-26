<?php

declare(strict_types=1);

/**
 * Migration Script: Remove Quantity Field
 * 
 * This script removes the "Quantity" field from all existing JSON files
 * in the data directory as part of the data structure simplification.
 * 
 * Usage: php migrate_jsons.php
 */

class QuantityFieldMigrator
{
    private string $dataDir;
    private int $filesProcessed = 0;
    private int $itemsUpdated = 0;
    private array $errors = [];

    public function __construct(string $dataDir = 'data')
    {
        $this->dataDir = $dataDir;
    }

    public function migrate(): void
    {
        echo "Starting Quantity field migration...\n";
        echo "Target directory: {$this->dataDir}\n\n";

        if (!is_dir($this->dataDir)) {
            echo "Error: Data directory '{$this->dataDir}' does not exist.\n";
            return;
        }

        // Find all JSON files in the data directory
        $jsonFiles = glob($this->dataDir . '/*.json');
        
        if (empty($jsonFiles)) {
            echo "No JSON files found in '{$this->dataDir}' directory.\n";
            return;
        }

        echo "Found " . count($jsonFiles) . " JSON files to process.\n\n";

        foreach ($jsonFiles as $filePath) {
            $this->processFile($filePath);
        }

        $this->printSummary();
    }

    private function processFile(string $filePath): void
    {
        $filename = basename($filePath);
        echo "Processing: {$filename}... ";

        try {
            // Read the file
            $content = file_get_contents($filePath);
            if ($content === false) {
                throw new Exception("Failed to read file");
            }

            // Parse JSON
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON: " . json_last_error_msg());
            }

            // Track changes
            $initialItemCount = $this->countItems($data);
            
            // Remove Quantity fields
            $cleanedData = $this->removeQuantityFields($data);
            
            // Count updated items
            $updatedInThisFile = $this->countQuantityRemovals($data, $cleanedData);
            $this->itemsUpdated += $updatedInThisFile;

            // Only write if changes were made
            if ($updatedInThisFile > 0) {
                // Create backup
                $backupPath = $filePath . '.backup.' . date('Y-m-d-H-i-s');
                copy($filePath, $backupPath);

                // Write cleaned data
                $result = file_put_contents(
                    $filePath, 
                    json_encode($cleanedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                );

                if ($result === false) {
                    throw new Exception("Failed to write cleaned data");
                }

                echo "✓ ({$updatedInThisFile} items updated, backup created)\n";
            } else {
                echo "✓ (no changes needed)\n";
            }

            $this->filesProcessed++;

        } catch (Exception $e) {
            echo "✗ Error: " . $e->getMessage() . "\n";
            $this->errors[] = "{$filename}: " . $e->getMessage();
        }
    }

    private function removeQuantityFields(array $data): array
    {
        // Ensure we have a valid structure
        if (!isset($data['Checklist']) || !is_array($data['Checklist'])) {
            return $data;
        }

        $cleaned = $data;
        $cleaned['Checklist'] = $this->cleanItems($data['Checklist']);
        
        return $cleaned;
    }

    private function cleanItems(array $items): array
    {
        $cleaned = [];
        
        foreach ($items as $item) {
            if (!is_array($item)) {
                $cleaned[] = $item;
                continue;
            }

            $cleanItem = $item;

            // Remove Quantity field if it exists
            if (array_key_exists('Quantity', $cleanItem)) {
                unset($cleanItem['Quantity']);
            }

            // Recursively clean nested categories
            if (isset($cleanItem['Type']) && $cleanItem['Type'] === 'Category' && 
                isset($cleanItem['Content']) && is_array($cleanItem['Content'])) {
                $cleanItem['Content'] = $this->cleanItems($cleanItem['Content']);
            }

            $cleaned[] = $cleanItem;
        }

        return $cleaned;
    }

    private function countItems(array $data): int
    {
        if (!isset($data['Checklist']) || !is_array($data['Checklist'])) {
            return 0;
        }

        return $this->countItemsRecursive($data['Checklist']);
    }

    private function countItemsRecursive(array $items): int
    {
        $count = 0;
        
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            
            $count++;
            
            if (isset($item['Type']) && $item['Type'] === 'Category' && 
                isset($item['Content']) && is_array($item['Content'])) {
                $count += $this->countItemsRecursive($item['Content']);
            }
        }
        
        return $count;
    }

    private function countQuantityRemovals(array $originalData, array $cleanedData): int
    {
        if (!isset($originalData['Checklist']) || !is_array($originalData['Checklist'])) {
            return 0;
        }

        return $this->countQuantityRemovalsRecursive($originalData['Checklist']);
    }

    private function countQuantityRemovalsRecursive(array $items): int
    {
        $count = 0;
        
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            
            // Count if this item had a Quantity field
            if (array_key_exists('Quantity', $item)) {
                $count++;
            }
            
            // Recurse into categories
            if (isset($item['Type']) && $item['Type'] === 'Category' && 
                isset($item['Content']) && is_array($item['Content'])) {
                $count += $this->countQuantityRemovalsRecursive($item['Content']);
            }
        }
        
        return $count;
    }

    private function printSummary(): void
    {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "Migration Summary\n";
        echo str_repeat("=", 50) . "\n";
        echo "Files processed: {$this->filesProcessed}\n";
        echo "Items updated: {$this->itemsUpdated}\n";
        
        if (!empty($this->errors)) {
            echo "Errors encountered: " . count($this->errors) . "\n";
            echo "\nError details:\n";
            foreach ($this->errors as $error) {
                echo "  - {$error}\n";
            }
        } else {
            echo "No errors encountered.\n";
        }
        
        echo "\nMigration completed successfully!\n";
        
        if ($this->itemsUpdated > 0) {
            echo "\nNote: Backup files were created for all modified files.\n";
            echo "You can find them with the pattern: *.json.backup.YYYY-MM-DD-HH-MM-SS\n";
        }
    }
}

// Check if script is being run directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'] ?? '')) {
    // Allow specifying custom data directory as command line argument
    $dataDir = $argv[1] ?? 'data';
    
    $migrator = new QuantityFieldMigrator($dataDir);
    $migrator->migrate();
}