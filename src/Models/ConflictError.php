<?php

declare(strict_types=1);

namespace FlexiList\Models;

class ConflictError extends \Exception
{
    public string $currentEtag;
    public array $currentData;

    public function __construct(string $message, string $currentEtag, array $currentData)
    {
        parent::__construct($message);
        $this->currentEtag = $currentEtag;
        $this->currentData = $currentData;
    }
}