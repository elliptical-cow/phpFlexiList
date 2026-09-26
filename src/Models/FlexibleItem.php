<?php

declare(strict_types=1);

namespace FlexiList\Models;

class FlexibleItem
{
    public ?string $id;
    public string $Type;
    public string $Name;
    public int $Order;
    
    // Item-specific fields
    public ?bool $Checked;
    public ?string $Notes;
    
    // Category-specific fields
    public ?array $Content;
    public ?bool $Expanded;

    public function __construct(array $data)
    {
        $this->validate($data);
        $this->populate($data);
    }

    public static function validate(array $data): void
    {
        $errors = [];

        // Validate Type (required)
        if (!isset($data['Type'])) {
            $errors[] = 'Type field is required';
        } elseif (!in_array($data['Type'], ['Item', 'Category'])) {
            $errors[] = 'Type must be either "Item" or "Category"';
        }

        // Validate Name (required)
        if (!isset($data['Name'])) {
            $errors[] = 'Name field is required';
        } elseif (!is_string($data['Name']) || trim($data['Name']) === '') {
            $errors[] = 'Name must be a non-empty string';
        } elseif (strlen($data['Name']) > 500) {
            $errors[] = 'Name must not exceed 500 characters';
        }

        // Validate Order (required)
        if (!isset($data['Order'])) {
            $errors[] = 'Order field is required';
        } elseif (!is_int($data['Order']) || $data['Order'] < 1) {
            $errors[] = 'Order must be a positive integer';
        }

        // Type-specific validation
        if (isset($data['Type'])) {
            if ($data['Type'] === 'Item') {
                self::validateItemFields($data, $errors);
            } elseif ($data['Type'] === 'Category') {
                self::validateCategoryFields($data, $errors);
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }
    }

    private static function validateItemFields(array $data, array &$errors): void
    {
        // Items should not have category fields
        if (isset($data['Content'])) {
            $errors[] = 'Item should not have Content field';
        }
        if (isset($data['Expanded'])) {
            $errors[] = 'Item should not have Expanded field';
        }

        // Validate item-specific fields
        if (isset($data['Checked']) && !is_bool($data['Checked'])) {
            $errors[] = 'Checked must be a boolean';
        }
        if (isset($data['Notes']) && !is_string($data['Notes'])) {
            $errors[] = 'Notes must be a string';
        } elseif (isset($data['Notes']) && strlen($data['Notes']) > 10000) {
            $errors[] = 'Notes must not exceed 10000 characters';
        }
    }

    private static function validateCategoryFields(array $data, array &$errors): void
    {
        // Categories should not have item fields
        $itemFields = ['Checked', 'Notes'];
        foreach ($itemFields as $field) {
            if (isset($data[$field])) {
                $errors[] = "Category should not have {$field} field";
            }
        }

        // Validate category-specific fields
        if (isset($data['Expanded']) && !is_bool($data['Expanded'])) {
            $errors[] = 'Expanded must be a boolean';
        }

        if (isset($data['Content'])) {
            if (!is_array($data['Content'])) {
                $errors[] = 'Content must be an array';
            } else {
                // Recursively validate nested items
                foreach ($data['Content'] as $index => $item) {
                    try {
                        self::validate($item);
                    } catch (ValidationException $e) {
                        foreach ($e->getErrors() as $error) {
                            $errors[] = "Content[{$index}]: {$error}";
                        }
                    }
                }
            }
        }
    }

    private function populate(array $data): void
    {
        $this->id = $data['id'] ?? null;
        $this->Type = $data['Type'];
        $this->Name = $data['Name'];
        $this->Order = $data['Order'];

        if ($this->Type === 'Item') {
            $this->Checked = $data['Checked'] ?? false;
            $this->Notes = $data['Notes'] ?? '';
            $this->Content = null;
            $this->Expanded = null;
        } elseif ($this->Type === 'Category') {
            $this->Checked = null;
            $this->Notes = null;
            $this->Content = isset($data['Content']) ? $this->parseContent($data['Content']) : [];
            $this->Expanded = $data['Expanded'] ?? true;
        }
    }

    private function parseContent(array $content): array
    {
        $parsedContent = [];
        foreach ($content as $item) {
            $parsedContent[] = new FlexibleItem($item);
        }
        return $parsedContent;
    }

    public function toArray(): array
    {
        $result = [
            'Type' => $this->Type,
            'Name' => $this->Name,
            'Order' => $this->Order,
        ];

        if ($this->id !== null) {
            $result['id'] = $this->id;
        }

        if ($this->Type === 'Item') {
            $result['Checked'] = $this->Checked;
            $result['Notes'] = $this->Notes;
        } elseif ($this->Type === 'Category') {
            $result['Content'] = array_map(fn($item) => $item->toArray(), $this->Content);
            $result['Expanded'] = $this->Expanded;
        }

        return $result;
    }

    public function isItem(): bool
    {
        return $this->Type === 'Item';
    }

    public function isCategory(): bool
    {
        return $this->Type === 'Category';
    }
}
