<?php

use App\Exceptions\ApiException;
use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureActorType;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [AssignRequestId::class]);
        $middleware->alias(['actor' => EnsureActorType::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Business rule violations are expected outcomes, not errors worth alerting on.
        $exceptions->dontReport([ApiException::class]);
        $exceptions->render(new ApiExceptionRenderer);
    })->create();
