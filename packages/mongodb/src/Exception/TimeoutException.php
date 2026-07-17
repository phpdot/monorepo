<?php

declare(strict_types=1);

/**
 * Thrown when an operation exceeds the maximum execution time.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\MongoDB\Exception;

final class TimeoutException extends MongoException
{
    /**
     * @param string $message Error message
     * @param string $operation Operation that timed out
     * @param string $collection Collection name
     * @param int $code Error code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = '',
        private readonly string $operation = '',
        private readonly string $collection = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the operation that timed out.
     *
     * @return string
     */
    public function getOperation(): string
    {
        return $this->operation;
    }

    /**
     * Get the collection name.
     *
     * @return string
     */
    public function getCollection(): string
    {
        return $this->collection;
    }
}
