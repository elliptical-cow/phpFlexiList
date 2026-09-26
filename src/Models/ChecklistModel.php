<?php

declare(strict_types=1);

namespace FlexiList\Models;

class ChecklistModel
{
    public array $Checklist;
    public array $Metadata;

    public function __construct(array $data)
    {
        $this->validate($data);
        $this->populate($data);
    }

    public static function validate(array $data): void
    {
        $errors = [];

        // Validate Metadata structure (optional)
        if (isset($data['Metadata'])) {
            if (!is_array($data['Metadata'])) {
                $errors[] = 'Metadata must be an array';
            } else {
                // Validate Title within Metadata
                if (isset($data['Metadata']['Title'])) {
                    if (!is_string($data['Metadata']['Title'])) {
                        $errors[] = 'Metadata Title must be a string';
                    } elseif (strlen($data['Metadata']['Title']) > 100) {
                        $errors[] = 'Metadata Title must not exceed 100 characters';
                    }
                }
                
                // Validate Hide_Checked within Metadata
                if (isset($data['Metadata']['Hide_Checked'])) {
                    if (!is_bool($data['Metadata']['Hide_Checked'])) {
                        $errors[] = 'Metadata Hide_Checked must be a boolean';
                    }
                }
            }
        }

        // Also support legacy Title field for backwards compatibility
        if (isset($data['Title'])) {
            if (!is_string($data['Title'])) {
                $errors[] = 'Title must be a string';
            } elseif (strlen($data['Title']) > 100) {
                $errors[] = 'Title must not exceed 100 characters';
            }
        }

        if (!isset($data['Checklist'])) {
            $errors[] = 'Checklist field is required';
        } elseif (!is_array($data['Checklist'])) {
            $errors[] = 'Checklist must be an array';
        } else {
            // Validate each item in the checklist
            foreach ($data['Checklist'] as $index => $item) {
                try {
                    FlexibleItem::validate($item);
                } catch (ValidationException $e) {
                    foreach ($e->getErrors() as $error) {
                        $errors[] = "Checklist[{$index}]: {$error}";
                    }
                }
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('Checklist validation failed', $errors);
        }
    }

    private function populate(array $data): void
    {
        // Handle Metadata - support both new structure and legacy format
        $title = 'Your Checklist'; // Default
        $hideChecked = false; // Default
        
        if (isset($data['Metadata']['Title'])) {
            // New structure: {"Metadata": {"Title": "..."}, ...}
            $title = $data['Metadata']['Title'];
            $this->Metadata = $data['Metadata'];
        } elseif (isset($data['Title'])) {
            // Legacy structure: {"Title": "...", ...}
            $title = $data['Title'];
            $this->Metadata = ['Title' => $title];
        } else {
            // No title provided, use default
            $this->Metadata = ['Title' => $title];
        }
        
        // Ensure Hide_Checked exists with default value
        if (!isset($this->Metadata['Hide_Checked'])) {
            $this->Metadata['Hide_Checked'] = $hideChecked;
        }
        
        $this->Checklist = [];
        foreach ($data['Checklist'] as $item) {
            $this->Checklist[] = new FlexibleItem($item);
        }
    }

    public function toArray(): array
    {
        return [
            'Metadata' => $this->Metadata,
            'Checklist' => array_map(fn($item) => $item->toArray(), $this->Checklist)
        ];
    }

    public static function createEmpty(): ChecklistModel
    {
        return new ChecklistModel([
            'Metadata' => [
                'Title' => 'Your Checklist',
                'Hide_Checked' => false
            ],
            'Checklist' => []
        ]);
    }

    // Helper method to get title easily
    public function getTitle(): string
    {
        return $this->Metadata['Title'] ?? 'Your Checklist';
    }

    // Helper method to set title easily
    public function setTitle(string $title): void
    {
        $this->Metadata['Title'] = $title;
    }
}