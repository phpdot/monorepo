<?php

declare(strict_types=1);

/**
 * Formatter Interface
 *
 * Contract for log record formatters.
 * Implementations convert a log record array into a string representation.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Log\Formatter;

interface FormatterInterface
{
    /**
     * Format a log record into a string.
     *
     * @param array<string, mixed> $record The log record to format
     *
     * @return string The formatted log entry
     */
    public function format(array $record): string;
}
