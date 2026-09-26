<?php

declare(strict_types=1);

namespace FlexiList\Models;

class JsonPatchOperation
{
    public string $op;
    public string $path;
    public mixed $value;
    public ?string $from;

    public function __construct(array $data)
    {
        $this->validate($data);
        $this->op = $data['op'];
        $this->path = $data['path'];
        $this->value = $data['value'] ?? null;
        $this->from = $data['from'] ?? null;
    }

    public static function validate(array $data): void
    {
        $errors = [];
        $validOps = ['add', 'remove', 'replace', 'move', 'copy', 'test'];

        if (!isset($data['op'])) {
            $errors[] = 'op field is required';
        } elseif (!in_array($data['op'], $validOps)) {
            $errors[] = 'op must be one of: ' . implode(', ', $validOps);
        }

        if (!isset($data['path'])) {
            $errors[] = 'path field is required';
        } elseif (!is_string($data['path']) || !str_starts_with($data['path'], '/')) {
            $errors[] = 'path must be a valid JSON Pointer starting with "/"';
        }

        // Value is required for add, replace, and test
        if (isset($data['op']) && in_array($data['op'], ['add', 'replace', 'test']) && !array_key_exists('value', $data)) {
            $errors[] = 'value field is required for ' . $data['op'] . ' operations';
        }

        // From is required for move and copy
        if (isset($data['op']) && in_array($data['op'], ['move', 'copy']) && !isset($data['from'])) {
            $errors[] = 'from field is required for ' . $data['op'] . ' operations';
        }

        if (!empty($errors)) {
            throw new ValidationException('JsonPatchOperation validation failed', $errors);
        }
    }

    public function toArray(): array
    {
        $result = [
            'op' => $this->op,
            'path' => $this->path
        ];

        if ($this->value !== null) {
            $result['value'] = $this->value;
        }

        if ($this->from !== null) {
            $result['from'] = $this->from;
        }

        return $result;
    }
}