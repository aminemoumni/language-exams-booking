<?php

namespace App\Exception;

/**
 * Domain exception that carries its own HTTP status code.
 *
 * Services throw this; controllers catch it and call getStatusCode()
 * — no hardcoded status codes in the HTTP layer.
 */
class AppException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
