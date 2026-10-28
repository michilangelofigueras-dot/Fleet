<?php
declare(strict_types=1);

/**
 * Base class for every error the API reports to the client.
 * Carries the HTTP status code that index.php should answer with.
 */
class ApiException extends Exception
{
    /** @var int */
    protected $status;

    public function __construct(string $message, int $status = 422, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->status = $status;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}
