<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\Driver;
use App\Models\Rider;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to riders or drivers: `actor:rider` / `actor:driver`.
 * Riders and drivers are separate tables, so the authenticated model's class
 * (resolved from the Sanctum token) is the source of truth for the role.
 */
class EnsureActorType
{
    private const TYPES = [
        'rider' => Rider::class,
        'driver' => Driver::class,
    ];

    public function handle(Request $request, Closure $next, string $type): Response
    {
        $expected = self::TYPES[$type] ?? throw new \InvalidArgumentException("Unknown actor type [{$type}].");

        if (! $request->user() instanceof $expected) {
            throw ApiException::forbidden('WRONG_ACCOUNT_TYPE', "This action is only available to {$type}s.");
        }

        return $next($request);
    }
}
