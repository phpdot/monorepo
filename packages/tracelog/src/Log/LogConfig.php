<?php

declare(strict_types=1);

/**
 * Log Config
 *
 * Immutable value object holding top-level logging configuration.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Log;

final readonly class LogConfig
{
    /**
     * Create a log configuration.
     *
     * @param string $basePath Base directory for log files
     * @param int $minLevel Minimum log level to process
     * @param string $defaultFormatter Default formatter type ('json' or 'text')
     * @param int $maxChannels Maximum number of cached channel handlers
     */
    public function __construct(
        public string $basePath = '/var/log/app',
        public int $minLevel = 100,
        public string $defaultFormatter = 'json',
        public int $maxChannels = 50,
    ) {}
}
