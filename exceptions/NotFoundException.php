<?php
declare(strict_types=1);

/** A vehicle, customer, rental, resource or action does not exist (HTTP 404). */
class NotFoundException extends ApiException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
