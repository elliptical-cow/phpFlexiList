<?php

declare(strict_types=1);

namespace FlexiList\Utils;

use Exception;

/**
 * Simple cURL-based HTTP client to replace GuzzleHttp\Client for FTP deployment
 */
class CurlHttpClient
{
    private float $timeout;
    
    public function __construct(float $timeout = 30.0)
    {
        $this->timeout = $timeout;
    }

    public function post(string $url, array $options = []): CurlResponse
    {
        return $this->request('POST', $url, $options);
    }

    public function get(string $url, array $options = []): CurlResponse
    {
        return $this->request('GET', $url, $options);
    }

    private function request(string $method, string $url, array $options = []): CurlResponse
    {
        $ch = curl_init();

        // Basic cURL options
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'FlexiList/1.0 (PHP cURL)',
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        // Set method
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        // Handle JSON data
        if (isset($options['json'])) {
            $jsonData = json_encode($options['json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        }

        // Handle headers
        $headers = [];
        if (isset($options['headers'])) {
            foreach ($options['headers'] as $name => $value) {
                $headers[] = "$name: $value";
            }
        }
        
        // Add Content-Type header for JSON
        if (isset($options['json']) && !isset($options['headers']['Content-Type'])) {
            $headers[] = 'Content-Type: application/json';
        }
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        // Handle timeout from options
        if (isset($options['timeout'])) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $options['timeout']);
        }

        // Execute request
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        
        $curlError = curl_error($ch);
        $curlErrorNo = curl_errno($ch);
        
        curl_close($ch);

        // Handle cURL errors
        if ($body === false || $curlErrorNo !== 0) {
            $errorMessage = !empty($curlError) ? $curlError : 'Unknown cURL error';
            
            if ($curlErrorNo === CURLE_OPERATION_TIMEOUTED) {
                throw new CurlException('Connection timeout: ' . $errorMessage, $curlErrorNo);
            }
            
            throw new CurlException('HTTP request failed: ' . $errorMessage, $curlErrorNo);
        }

        return new CurlResponse($httpCode, $body, $contentType);
    }
}

/**
 * Simple response class to mimic Guzzle response structure
 */
class CurlResponse
{
    private int $statusCode;
    private string $body;
    private string $contentType;

    public function __construct(int $statusCode, string $body, string $contentType = '')
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->contentType = $contentType;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): CurlResponseBody
    {
        return new CurlResponseBody($this->body);
    }
}

/**
 * Simple response body class to mimic Guzzle response body structure
 */
class CurlResponseBody
{
    private string $contents;

    public function __construct(string $contents)
    {
        $this->contents = $contents;
    }

    public function getContents(): string
    {
        return $this->contents;
    }

    public function __toString(): string
    {
        return $this->contents;
    }
}

/**
 * Exception classes to replace Guzzle exceptions
 */
class CurlException extends Exception
{
    private bool $hasResponse = false;
    private ?CurlResponse $response = null;

    public function hasResponse(): bool
    {
        return $this->hasResponse;
    }

    public function getResponse(): ?CurlResponse
    {
        return $this->response;
    }

    public function setResponse(CurlResponse $response): void
    {
        $this->response = $response;
        $this->hasResponse = true;
    }
}

class RequestException extends CurlException {}
class ClientException extends RequestException {}