<?php

declare(strict_types=1);

namespace FlexiList\Security;

use RuntimeException;

final class RateLimiter
{
    public function __construct(private readonly string $directory)
    {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create rate-limit directory');
        }
    }

    public function consume(string $key, int $limit, int $windowSeconds): bool
    {
        $path = rtrim($this->directory, '/') . '/' . hash('sha256', $key) . '.json';
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            return false;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return false;
            }
            $contents = stream_get_contents($handle);
            $state = json_decode($contents ?: '', true);
            $now = time();
            if (!is_array($state) || ($state['reset_at'] ?? 0) <= $now) {
                $state = ['count' => 0, 'reset_at' => $now + $windowSeconds];
            }
            if ($state['count'] >= $limit) {
                return false;
            }

            $state['count']++;
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($state));
            fflush($handle);
            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}

