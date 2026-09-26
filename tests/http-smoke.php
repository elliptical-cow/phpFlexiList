<?php

declare(strict_types=1);

$baseUrl = getenv('FLEXILIST_TEST_URL') ?: 'http://127.0.0.1:18089';

$request = static function (string $method, string $path, array $headers = [], ?string $body = null) use ($baseUrl): array {
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $body ?? '',
            'ignore_errors' => true,
            'timeout' => 5,
        ],
    ]);
    $responseBody = file_get_contents($baseUrl . $path, false, $context);
    $responseHeaders = $http_response_header ?? [];
    preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $matches);
    return [(int) ($matches[1] ?? 0), (string) $responseBody];
};

[$status, $body] = $request('POST', '/api/lists');
if ($status !== 201) {
    throw new RuntimeException("Expected create status 201, got {$status}: {$body}");
}
$credentials = json_decode($body, true, flags: JSON_THROW_ON_ERROR);

[$status] = $request('GET', '/api/list/' . $credentials['id']);
if ($status !== 401) {
    throw new RuntimeException("Expected unauthorized status 401, got {$status}");
}

$authorization = ['Authorization: Bearer ' . $credentials['token']];
[$status, $body] = $request('GET', '/api/list/' . $credentials['id'], $authorization);
if ($status !== 200 || !is_array(json_decode($body, true)['Checklist'] ?? null)) {
    throw new RuntimeException("Authorized list request failed: {$status} {$body}");
}

[$status, $body] = $request('GET', '/vendor/vue.global.prod.js');
if ($status !== 200 || !str_contains($body, 'var Vue=')) {
    throw new RuntimeException('Static vendor asset was not served');
}

[$status] = $request('DELETE', '/api/list/' . $credentials['id'], $authorization);
if ($status !== 200) {
    throw new RuntimeException("Expected delete status 200, got {$status}");
}

fwrite(STDOUT, "HTTP smoke test passed.\n");

