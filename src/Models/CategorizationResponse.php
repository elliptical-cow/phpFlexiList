<?php

declare(strict_types=1);

namespace FlexiList\Models;

class CategorizationResponse
{
    public array $assignments;
    public bool $success;
    public string $message;

    public function __construct(array $assignments, bool $success, string $message)
    {
        $this->assignments = $assignments;
        $this->success = $success;
        $this->message = $message;
    }

    public function toArray(): array
    {
        return [
            'assignments' => array_map(fn($assignment) => $assignment->toArray(), $this->assignments),
            'success' => $this->success,
            'message' => $this->message
        ];
    }
}