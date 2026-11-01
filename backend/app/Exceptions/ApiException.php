<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Expected business error that maps directly onto an API error response.
 * Rendered by ApiExceptionRenderer as {"error": {"code", "message", "details"}}.
 */
class ApiException extends RuntimeException
{
    /** @param array<string, mixed> $details */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    /** @param array<string, mixed> $details */
    public static function conflict(string $errorCode, string $message, array $details = []): self
    {
        return new self(409, $errorCode, $message, $details);
    }

    public static function forbidden(string $errorCode, string $message): self
    {
        return new self(403, $errorCode, $message);
    }

    /** @param array<string, mixed> $details */
    public static function unprocessable(string $errorCode, string $message, array $details = []): self
    {
        return new self(422, $errorCode, $message, $details);
    }
}
