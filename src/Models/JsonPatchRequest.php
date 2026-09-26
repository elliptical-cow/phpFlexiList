<?php

declare(strict_types=1);

namespace FlexiList\Models;

class JsonPatchRequest
{
    public array $operations;

    public function __construct(array $data)
    {
        $this->validate($data);
        $this->operations = array_map(fn($op) => new JsonPatchOperation($op), $data['operations']);
    }

    public static function validate(array $data): void
    {
        $errors = [];

        if (!isset($data['operations'])) {
            $errors[] = 'operations field is required';
        } elseif (!is_array($data['operations'])) {
            $errors[] = 'operations must be an array';
        } else {
            foreach ($data['operations'] as $index => $operation) {
                try {
                    JsonPatchOperation::validate($operation);
                } catch (ValidationException $e) {
                    foreach ($e->getErrors() as $error) {
                        $errors[] = "operations[{$index}]: {$error}";
                    }
                }
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('JsonPatchRequest validation failed', $errors);
        }
    }

    public function toArray(): array
    {
        return [
            'operations' => array_map(fn($op) => $op->toArray(), $this->operations)
        ];
    }
}