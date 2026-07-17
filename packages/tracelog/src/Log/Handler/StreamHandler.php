<?php

declare(strict_types=1);

/**
 * Stream Handler
 *
 * Writes formatted log records to a file stream using file_put_contents.
 * Creates the target directory if it does not exist.
 * Silently fails on write errors to prevent logging from crashing the application.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Log\Handler;

use PHPdot\TraceLog\Log\Formatter\FormatterInterface;

final class StreamHandler implements HandlerInterface
{
    /**
     * Create a stream handler.
     *
     * @param string $path Absolute path to the log file
     * @param FormatterInterface $formatter The formatter to use for log records
     * @param int $minLevel Minimum log level this handler processes
     */
    public function __construct(
        private readonly string $path,
        private readonly FormatterInterface $formatter,
        private readonly int $minLevel = 100,
    ) {}

    /**
     * Handle a log record by formatting and writing it to the file.
     *
     * Silently fails if the directory cannot be created or the file cannot be
     * written — logging must never crash the application, so a formatter throw
     * or write failure drops the record.
     *
     * @param array<string, mixed> $record The log record to handle
     *
     * @return void
     */
    public function handle(array $record): void
    {
        $rawLevel = $record['level'] ?? 0;
        $level = is_int($rawLevel) ? $rawLevel : 0;

        if (!$this->isHandling($level)) {
            return;
        }

        $directory = dirname($this->path);

        if (!is_dir($directory)) {
            @mkdir($directory, 0o755, true);
        }

        try {
            $formatted = $this->formatter->format($record);
            @file_put_contents($this->path, $formatted, FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
        }
    }

    /**
     * Determine whether this handler processes the given log level.
     *
     * @param int $level The log level to check
     *
     * @return bool True if the level meets or exceeds the minimum
     */
    public function isHandling(int $level): bool
    {
        return $level >= $this->minLevel;
    }
}
