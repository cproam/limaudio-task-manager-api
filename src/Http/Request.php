<?php

namespace App\Http;

class Request
{
    public string $method;
    public string $path;
    public array $headers;
    public array $query;
    public array $body;

    public function __construct()
    {
    $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    // Prefer REQUEST_URI which keeps the original path (works with Apache rewrite and PHP built-in server)
    $this->path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $this->headers = $this->getAllHeaders();
        $this->query = $_GET ?? [];
        $this->body = $this->parseJsonBody();
    }

    private function getAllHeaders(): array
    {
        // getallheaders() is not always available (e.g., FastCGI on Windows)
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            return is_array($headers) ? $headers : [];
        }
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$key] = $value;
            }
        }
        return $headers;
    }

    private function parseJsonBody(): array
    {
        $input = file_get_contents('php://input');
        if (!$input) return [];
        $data = json_decode($input, true);
        return is_array($data) ? $data : [];
    }
}
