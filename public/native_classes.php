<?php

declare(strict_types=1);

// Native HTTP Helper Classes for replacing PSR-7

class NativeRequest
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $headers,
        public readonly string $body,
        public readonly array $pathParams = []
    ) {}

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getHeaderLine(string $name): string
    {
        $name = strtolower($name);
        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $name) {
                return is_array($value) ? implode(', ', $value) : $value;
            }
        }
        return '';
    }

    public function getPathParam(string $name): ?string
    {
        return $this->pathParams[$name] ?? null;
    }

    public function getQueryParam(string $name): ?string
    {
        return $this->query[$name] ?? null;
    }
}

class NativeResponse
{
    private int $statusCode = 200;
    private array $headers = [];
    private string $body = '';

    public function withStatus(int $code): self
    {
        $new = clone $this;
        $new->statusCode = $code;
        return $new;
    }

    public function withHeader(string $name, string $value): self
    {
        $new = clone $this;
        $new->headers[$name] = $value;
        return $new;
    }

    public function write(string $content): self
    {
        $this->body = $content;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        echo $this->body;
    }
}

// Native Router
class NativeRouter
{
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->addRoute('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->addRoute('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable $handler): void
    {
        $this->addRoute('PUT', $pattern, $handler);
    }

    public function patch(string $pattern, callable $handler): void
    {
        $this->addRoute('PATCH', $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): void
    {
        $this->addRoute('DELETE', $pattern, $handler);
    }

    private function addRoute(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler
        ];
    }

    public function dispatch(NativeRequest $request): ?NativeResponse
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->getMethod()) {
                continue;
            }

            $pathParams = $this->matchRoute($route['pattern'], $request->getPath());
            if ($pathParams !== null) {
                $requestWithParams = new NativeRequest(
                    $request->method,
                    $request->path,
                    $request->query,
                    $request->headers,
                    $request->body,
                    $pathParams
                );

                return call_user_func($route['handler'], $requestWithParams);
            }
        }

        return null;
    }

    private function matchRoute(string $pattern, string $path): ?array
    {
        // Convert pattern like '/api/list/{list_id}' to regex
        $regex = preg_replace('/\{([^}]+)\}/', '([^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $path, $matches)) {
            // Extract parameter names
            preg_match_all('/\{([^}]+)\}/', $pattern, $paramNames);
            $params = [];
            
            for ($i = 1; $i < count($matches); $i++) {
                $paramName = $paramNames[1][$i - 1];
                $params[$paramName] = $matches[$i];
            }

            return $params;
        }

        return null;
    }
}