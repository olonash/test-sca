<?php

declare(strict_types=1);

namespace ScalE\Controllers;

use ScalE\Http\Response;

abstract class BaseController
{
    protected function jsonResponse(array $payload, int $statusCode = 200): Response
    {
        return Response::json($payload, $statusCode);
    }
}
