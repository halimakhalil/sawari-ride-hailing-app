<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tags every API request with a correlation id (taken from X-Request-Id when the
 * caller supplies a sane one) that is added to all log lines and echoed back in
 * the response, then writes one structured access-log line per request.
 */
class AssignRequestId
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->headers->get(self::HEADER, '');
        $requestId = preg_match('/^[A-Za-z0-9\-_.]{8,64}$/', $incoming) ? $incoming : (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);
        Log::shareContext(['request_id' => $requestId]);

        $startedAt = microtime(true);
        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        Log::info('http.request', [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'actor' => $request->user() ? class_basename($request->user()).':'.$request->user()->getAuthIdentifier() : null,
        ]);

        return $response;
    }
}
