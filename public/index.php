<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ScalE\Http\Request;
use ScalE\Http\Router;

$request = Request::fromGlobals();
$router = new Router();
$response = $router->dispatch($request);
$response->send();
