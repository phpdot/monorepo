<?php

declare(strict_types=1);

/**
 * Thrown when a write violates a unique index constraint.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\MongoDB\Exception;

final class DuplicateKeyException extends WriteException
{
    /**
     * @param string $message Error message
     * @param string $collection Collection name
     * @param string $duplicateKey The index name that caused the duplicate
     * @param int $code Error code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = '',
        string $collection = '',
        private readonly string $duplicateKey = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, '', $collection, $code, $previous);
    }

    /**
     * Get the index name that caused the duplicate key violation.
     *
     * @return string
     */
    public function getDuplicateKey(): string
    {
        return $this->duplicateKey;
    }
}
