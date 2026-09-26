<?php

declare(strict_types=1);

namespace FlexiList\Models;

class ListVersion
{
    public array $data;
    public string $etag;

    public function __construct(array $data, ?string $etag = null)
    {
        $this->data = $data;
        $this->etag = $etag ?? $this->generateEtag($data);
    }

    private function generateEtag(array $data): string
    {
        $content = json_encode($data, JSON_UNESCAPED_UNICODE);
        return md5($content);
    }

    public function updateData(array $newData): ListVersion
    {
        return new ListVersion($newData);
    }
}