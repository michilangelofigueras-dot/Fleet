<?php
declare(strict_types=1);

/** The data folder or records.json could not be read, written or locked (HTTP 500). */
class StorageException extends ApiException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 500, $previous);
    }
}
