<?php

declare(strict_types=1);

namespace FlexiList\Models;

class RetryablePatchError extends \Exception
{
    public int $retryAfter;

    public function __construct(string $message, int $retryAfter = 1)
    {
        parent::__construct($message);
        $this->retryAfter = $retryAfter;
    }
}