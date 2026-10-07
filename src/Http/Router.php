<?php

declare(strict_types=1);

namespace ScalE\Http;

use ScalE\Controllers\CustomerController;
use ScalE\Controllers\EventController;
use ScalE\Controllers\SegmentController;

final class Router
{
    public function dispatch(Request $request): Response
    {
        $path = $request->path();

        if ($path === '/health') {
            return Response::json(['status' => 'ok']);
        }

        if ($path === '/' || $path === '/dashboard') {
            $html = file_get_contents(__DIR__ . '/../../templates/dashboard.html');
            if ($html === false) {
                return Response::json(['error' => 'Dashboard template not found.'], 500);
            }

            return new HtmlResponse($html, 200);
        }

        if ($path === '/dashboard-api' || str_starts_with($path, '/dashboard-api/')) {
            $path = '/api' . substr($path, strlen('/dashboard-api'));
        } elseif ($path === '/api' || str_starts_with($path, '/api/')) {
            if (!$this->hasValidApiKey($request)) {
                return Response::json(['error' => 'Unauthorized.'], 401);
            }
        }

        if ($request->method() === 'POST' && $path === '/api/events') {
            return (new EventController())->ingest($request);
        }

        if ($request->method() === 'GET' && preg_match('#^/api/customers/([0-9]+)$#', $path, $matches) === 1) {
            $request = new Request('GET', '/api/customers/' . $matches[1], $request->query(), []);
            return (new CustomerController())->show($request);
        }

        if ($request->method() === 'POST' && $path === '/api/segments/query') {
            return (new SegmentController())->query($request);
        }

        if ($request->method() === 'GET' && $path === '/api/customers') {
            return (new CustomerController())->index($request);
        }

        return Response::json(['error' => 'Not found.'], 404);
    }

    private function hasValidApiKey(Request $request): bool
    {
        $configuredKeys = array_filter(array_map('trim', explode(',', getenv('API_KEYS') ?: '')));
        $authorization = $request->header('Authorization');

        if ($configuredKeys === [] || $authorization === null
            || preg_match('/^Bearer\s+(\S+)$/iD', trim($authorization), $matches) !== 1
        ) {
            return false;
        }

        foreach ($configuredKeys as $configuredKey) {
            if (hash_equals($configuredKey, $matches[1])) {
                return true;
            }
        }

        return false;
    }
}
