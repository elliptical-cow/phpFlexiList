<?php

declare(strict_types=1);

namespace FlexiList\Models;

class ItemAssignment
{
    public string $item;
    public ?string $category;

    public function __construct(string $item, ?string $category = null)
    {
        $this->item = $item;
        $this->category = $category;
    }

    public function toArray(): array
    {
        return [
            'item' => $this->item,
            'category' => $this->category
        ];
    }
}