<?php

declare(strict_types=1);

/**
 * Thrown when a bulk write operation partially or fully fails.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\MongoDB\Exception;

use MongoDB\BulkWriteResult;

final class BulkWriteException extends WriteException
{
    private ?BulkWriteResult $partialResult = null;

    /**
     * Set the partial result from the bulk write operation.
     *
     * @param BulkWriteResult $result
     *
     * @return void
     */
    public function setPartialResult(BulkWriteResult $result): void
    {
        $this->partialResult = $result;
    }

    /**
     * Get the partial result, if any operations succeeded before the failure.
     *
     * @return ?BulkWriteResult
     */
    public function getPartialResult(): ?BulkWriteResult
    {
        return $this->partialResult;
    }
}
