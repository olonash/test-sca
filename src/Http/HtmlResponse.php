<?php

declare(strict_types=1);

namespace ScalE\Http;

final class HtmlResponse extends Response
{
    public function __construct(
        string $html,
        int $statusCode = 200
    ) {
        parent::__construct($statusCode, ['html' => $html]);
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        header('Content-Type: text/html; charset=utf-8');
        echo $this->data['html'];
    }
}
