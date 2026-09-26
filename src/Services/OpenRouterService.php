<?php

declare(strict_types=1);

namespace FlexiList\Services;

use FlexiList\Utils\CurlHttpClient;
use FlexiList\Utils\CurlException;
use FlexiList\Utils\RequestException;
use FlexiList\Utils\ClientException;
use FlexiList\Models\ItemAssignment;
use FlexiList\Models\CategorizationResponse;
use Exception;

class LLMDebugLogger
{
    private bool $debugEnabled;
    private ?string $logFile;

    public function __construct(bool $debugEnabled)
    {
        $this->debugEnabled = $debugEnabled;
        $this->logFile = null;
        
        if ($this->debugEnabled) {
            $timestamp = date('Ymd_His');
            $this->logFile = "LLM-Log-{$timestamp}.log";
            error_log("LLM Debug logging enabled. Log file: {$this->logFile}");
        }
    }

    public function logRequest(string $prompt, array $payload): void
    {
        if (!$this->debugEnabled || !$this->logFile) {
            return;
        }

        $content = "\n" . str_repeat('=', 80) . "\n";
        $content .= "TIMESTAMP: " . date('c') . "\n";
        $content .= "REQUEST PROMPT:\n{$prompt}\n";
        $content .= "\nFULL PAYLOAD:\n" . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

        file_put_contents($this->logFile, $content, FILE_APPEND | LOCK_EX);
    }

    public function logResponse(int $statusCode, string $rawResponse, ?array $parsedData = null, ?string $error = null): void
    {
        if (!$this->debugEnabled || !$this->logFile) {
            return;
        }

        $content = "\nRESPONSE STATUS: {$statusCode}\n";
        $content .= "RAW RESPONSE:\n{$rawResponse}\n";
        
        if ($parsedData) {
            $content .= "\nPARSED DATA:\n" . json_encode($parsedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
        
        if ($error) {
            $content .= "\nERROR: {$error}\n";
        }
        
        $content .= str_repeat('=', 80) . "\n\n";

        file_put_contents($this->logFile, $content, FILE_APPEND | LOCK_EX);
    }
}

class OpenRouterService
{
    private ?string $apiKey;
    private string $model;
    private int $maxContext;
    private float $timeout;
    private string $baseUrl;
    private bool $debugEnabled;
    private string $backendUrl;
    private LLMDebugLogger $logger;
    private CurlHttpClient $httpClient;

    public function __construct(array $config, string $backendUrl = '')
    {
        $this->apiKey = $config['api_key'] ?? null;
        $this->model = $config['model'] ?? 'google/gemini-flash-1.5';
        $this->maxContext = $config['max_context'] ?? 5000;
        $this->timeout = $config['timeout'] ?? 3.0;
        $this->baseUrl = $config['base_url'] ?? 'https://openrouter.ai/api/v1';
        $this->debugEnabled = $config['debug'] ?? false;
        $this->backendUrl = $backendUrl;
        $this->logger = new LLMDebugLogger($this->debugEnabled);
        
        $this->httpClient = new CurlHttpClient($this->timeout);

        if (!$this->apiKey || $this->apiKey === 'your_api_key_here') {
            error_log('Warning: OPENROUTER_API_KEY not configured properly');
        }
    }

    private function getBackendUrl(): string
    {
        return $this->backendUrl;
    }

    private function createPrompt(array $items, array $categories, string $listTitle = ''): string
    {
        require_once __DIR__ . '/Prompts/auto_categorization_prompt.php';
        return createAutoCategorizationPrompt($items, $categories, $listTitle);
    }

    private function validateContextLength(string $prompt): bool
    {
        return strlen($prompt) <= $this->maxContext;
    }

    private function parseLLMResponse(string $content): array
    {
        // Strategy 1: Direct JSON parsing
        try {
            $result = json_decode(trim($content), true);
            if (is_array($result)) {
                return $this->validateAssignments($result);
            }
        } catch (Exception $e) {
            // Continue to next strategy
        }

        // Strategy 2: Extract JSON block from response
        if (preg_match('/\[.*?\]/s', $content, $matches)) {
            try {
                $result = json_decode($matches[0], true);
                if (is_array($result)) {
                    return $this->validateAssignments($result);
                }
            } catch (Exception $e) {
                // Continue to next strategy
            }
        }

        // Strategy 3: Line-by-line search
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '[') && str_ends_with($line, ']')) {
                try {
                    $result = json_decode($line, true);
                    if (is_array($result)) {
                        return $this->validateAssignments($result);
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        }

        // If all strategies fail
        throw new Exception('No valid JSON array found in LLM response');
    }

    private function validateAssignments(array $assignments): array
    {
        $validated = [];
        foreach ($assignments as $assignment) {
            if (is_array($assignment) && isset($assignment['item'])) {
                $validated[] = [
                    'item' => (string)$assignment['item'],
                    'category' => $assignment['category'] ?? null
                ];
            }
        }
        return $validated;
    }

    public function categorizeItems(array $items, array $categories, string $listTitle = ''): CategorizationResponse
    {
        if (!$this->apiKey || $this->apiKey === 'your_api_key_here') {
            throw new Exception('OpenRouter API key not configured');
        }

        // Create prompt
        $prompt = $this->createPrompt($items, $categories, $listTitle);

        // Validate context length
        if (!$this->validateContextLength($prompt)) {
            throw new Exception("Request too long. Maximum {$this->maxContext} characters allowed.");
        }

        // Prepare request payload
        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 1000,
            'temperature' => 0.1
        ];

        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'HTTP-Referer' => $this->getBackendUrl(),
            'X-Title' => 'FlexiList Auto-Categorization'
        ];

        // Log request
        $this->logger->logRequest($prompt, $payload);

        try {
            $response = $this->httpClient->post(
                $this->baseUrl . '/chat/completions',
                [
                    'json' => $payload,
                    'headers' => $headers,
                    'timeout' => $this->timeout
                ]
            );

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();

            if ($statusCode !== 200) {
                $this->logger->logResponse($statusCode, $responseBody, null, 'API Error');
                
                error_log("OpenRouter request failed with HTTP {$statusCode}");

                // Specific error messages based on status code
                $detailMsg = match ($statusCode) {
                    400 => 'Bad Request - possibly invalid model name or request format',
                    401 => 'Unauthorized - API key invalid',
                    403 => 'Forbidden - API key has no permission for this model',
                    429 => 'Rate Limit - too many requests',
                    default => "HTTP {$statusCode}"
                };

                throw new Exception("OpenRouter API error: {$detailMsg}");
            }

            $result = json_decode($responseBody, true);

            // Extract content from response
            if (!isset($result['choices']) || empty($result['choices'])) {
                $this->logger->logResponse($statusCode, $responseBody, null, 'Invalid API response structure');
                throw new Exception('Invalid response from OpenRouter API');
            }

            $content = $result['choices'][0]['message']['content'];

            // Log successful response
            $this->logger->logResponse($statusCode, $content);

            // Parse with enhanced error handling
            try {
                $assignments = $this->parseLLMResponse($content);
                $assignmentObjects = array_map(
                    fn($assignment) => new ItemAssignment($assignment['item'], $assignment['category']),
                    $assignments
                );

                $response = new CategorizationResponse(
                    $assignmentObjects,
                    true,
                    'Categorization successful'
                );

                // Log parsed result
                $this->logger->logResponse($statusCode, $content, $response->toArray());
                return $response;

            } catch (Exception $parseError) {
                // Log parsing error
                $this->logger->logResponse($statusCode, $content, null, "Parse Error: " . $parseError->getMessage());
                error_log("Parse error: " . $parseError->getMessage());
                throw new Exception('Invalid LLM response');
            }

        } catch (CurlException $e) {
            $this->logger->logResponse(0, '', null, "HTTP Error: " . $e->getMessage());
            error_log("HTTP error: " . $e->getMessage());
            throw new Exception('OpenRouter Service not available');
        }
    }

}
