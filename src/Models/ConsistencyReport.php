<?php

declare(strict_types=1);

namespace FlexiList\Models;

class ConsistencyReport
{
    public bool $isValid;
    public array $errors;
    public array $warnings;
    public bool $repaired;

    public function __construct(bool $isValid, array $errors = [], array $warnings = [], bool $repaired = false)
    {
        $this->isValid = $isValid;
        $this->errors = $errors;
        $this->warnings = $warnings;
        $this->repaired = $repaired;
    }
}