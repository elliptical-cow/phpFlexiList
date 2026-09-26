<?php

declare(strict_types=1);

namespace FlexiList\Security;

use RuntimeException;

final class ListAccessService
{
    private string $accessDirectory;

    public function __construct(private readonly string $dataDirectory)
    {
        $this->accessDirectory = rtrim($dataDirectory, '/') . '/.access';
        if (!is_dir($this->accessDirectory) && !mkdir($this->accessDirectory, 0700, true) && !is_dir($this->accessDirectory)) {
            throw new RuntimeException('Unable to create access metadata directory');
        }
    }

    /** @return array{id: string, token: string} */
    public function issue(): array
    {
        do {
            $id = bin2hex(random_bytes(12));
            $path = $this->path($id);
        } while (is_file($path) || is_file(rtrim($this->dataDirectory, '/') . "/{$id}.json"));

        $token = self::base64UrlEncode(random_bytes(32));
        $metadata = json_encode([
            'token_hash' => hash('sha256', $token),
            'created_at' => gmdate(DATE_ATOM),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($metadata === false || file_put_contents($path, $metadata, LOCK_EX) === false) {
            throw new RuntimeException('Unable to persist access metadata');
        }
        chmod($path, 0600);

        return ['id' => $id, 'token' => $token];
    }

    public function assertAuthorized(string $listId, string $authorizationHeader): void
    {
        if (!preg_match('/^Bearer\s+(.+)$/i', trim($authorizationHeader), $matches)) {
            throw new AccessDeniedException('A bearer token is required');
        }

        $metadata = $this->readMetadata($listId);
        $candidateHash = hash('sha256', $matches[1]);
        if (!hash_equals((string) $metadata['token_hash'], $candidateHash)) {
            throw new AccessDeniedException('Invalid bearer token');
        }
    }

    public function revoke(string $listId): void
    {
        $path = $this->path($listId);
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('Unable to remove access metadata');
        }
    }

    private function readMetadata(string $listId): array
    {
        $path = $this->path($listId);
        if (!is_file($path)) {
            throw new AccessDeniedException('List access metadata not found');
        }

        $metadata = json_decode((string) file_get_contents($path), true);
        if (!is_array($metadata) || !is_string($metadata['token_hash'] ?? null)) {
            throw new RuntimeException('Invalid access metadata');
        }
        return $metadata;
    }

    private function path(string $listId): string
    {
        if (!preg_match('/^[a-f0-9]{24}$/', $listId)) {
            throw new AccessDeniedException('Invalid list identifier');
        }
        return $this->accessDirectory . '/' . $listId . '.json';
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

