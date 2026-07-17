<?php

declare(strict_types=1);

/**
 * Channel Config
 *
 * Immutable value object holding configuration for channel-based log routing.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Log\Channel;

final readonly class ChannelConfig
{
    /**
     * Create a channel configuration.
     *
     * @param string $basePath Base directory for log files
     * @param int $minLevel Minimum log level to process
     * @param int $maxChannels Maximum number of cached channel handlers
     */
    public function __construct(
        public string $basePath,
        public int $minLevel = 100,
        public int $maxChannels = 50,
    ) {}
}
