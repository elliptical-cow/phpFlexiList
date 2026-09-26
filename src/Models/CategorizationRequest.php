<?php

declare(strict_types=1);

namespace FlexiList\Models;

class CategorizationRequest
{
    public array $items;
    public array $categories;

    public function __construct(array $data)
    {
        $this->validate($data);
        $this->items = $data['items'];
        $this->categories = $data['categories'];
    }

    public static function validate(array $data): void
    {
        $errors = [];

        if (!isset($data['items'])) {
            $errors[] = 'items field is required';
        } elseif (!is_array($data['items'])) {
            $errors[] = 'items must be an array';
        } else {
            foreach ($data['items'] as $index => $item) {
                if (!is_string($item)) {
                    $errors[] = "items[{$index}] must be a string";
                }
            }
        }

        if (!isset($data['categories'])) {
            $errors[] = 'categories field is required';
        } elseif (!is_array($data['categories'])) {
            $errors[] = 'categories must be an array';
        } else {
            foreach ($data['categories'] as $index => $category) {
                if (!is_string($category)) {
                    $errors[] = "categories[{$index}] must be a string";
                }
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('CategorizationRequest validation failed', $errors);
        }
    }
}