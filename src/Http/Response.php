<?php

declare(strict_types=1);

namespace ScalE\Http;

class Response
{
    public function __construct(
        protected readonly int $statusCode = 200,
        protected readonly array $data = []
    ) {
    }

    public static function json(array $data, int $statusCode = 200): self
    {
        return new self($statusCode, $data);
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
