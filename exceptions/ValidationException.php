<?php
declare(strict_types=1);

/** The request was understood but a field or business rule was rejected (HTTP 422). */
class ValidationException extends ApiException
{
    public function __construct(string $message, int $status = 422, ?Throwable $previous = null)
    {
        parent::__construct($message, $status, $previous);
    }
}
