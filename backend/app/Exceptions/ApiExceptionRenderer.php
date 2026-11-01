<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Converts any exception thrown under /api into the single error envelope:
 *
 *   {"error": {"code": "RIDE_ALREADY_ASSIGNED", "message": "...", "details": {}, "request_id": "..."}}
 */
class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        [$status, $code, $message, $details] = match (true) {
            $e instanceof ApiException => [$e->status, $e->errorCode, $e->getMessage(), $e->details],
            $e instanceof ValidationException => [422, 'VALIDATION_FAILED', 'The given data was invalid.', $e->errors()],
            $e instanceof AuthenticationException => [401, 'UNAUTHENTICATED', 'Authentication is required.', []],
            $e instanceof AuthorizationException,
            $e instanceof AccessDeniedHttpException => [403, 'FORBIDDEN', 'You are not allowed to perform this action.', []],
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => [404, 'NOT_FOUND', $this->notFoundMessage($e), []],
            $e instanceof HttpExceptionInterface => [$e->getStatusCode(), $this->httpCode($e->getStatusCode()), $e->getMessage() ?: 'Request failed.', []],
            default => [500, 'INTERNAL_ERROR', 'Something went wrong on our side. Please try again.', []],
        };

        if ($status === 500 && config('app.debug')) {
            $details = ['exception' => $e::class, 'message' => $e->getMessage()];
        }

        $response = response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => (object) $details,
                'request_id' => $request->attributes->get('request_id'),
            ],
        ], $status);

        if ($e instanceof HttpExceptionInterface) {
            $response->withHeaders($e->getHeaders()); // e.g. Retry-After on 429
        }

        return $response;
    }

    private function notFoundMessage(Throwable $e): string
    {
        $previous = $e instanceof ModelNotFoundException ? $e : $e->getPrevious();

        if ($previous instanceof ModelNotFoundException) {
            return class_basename($previous->getModel()).' not found.';
        }

        return 'The requested resource was not found.';
    }

    private function httpCode(int $status): string
    {
        return match ($status) {
            405 => 'METHOD_NOT_ALLOWED',
            429 => 'TOO_MANY_REQUESTS',
            503 => 'SERVICE_UNAVAILABLE',
            default => 'HTTP_ERROR',
        };
    }
}
