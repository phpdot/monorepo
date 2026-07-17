<?php

declare(strict_types=1);

/**
 * Channel Manager
 *
 * Manages per-channel log handlers with lazy creation and LRU eviction.
 * Each channel maps to a dedicated StreamHandler writing to its own log file.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Log\Channel;

use PHPdot\TraceLog\Log\Formatter\FormatterInterface;
use PHPdot\TraceLog\Log\Formatter\JsonFormatter;
use PHPdot\TraceLog\Log\Handler\HandlerInterface;
use PHPdot\TraceLog\Log\Handler\StreamHandler;

final class ChannelManager
{
    /**
     * @var array<string, HandlerInterface>
     */
    private array $handlers = [];

    /**
     * @var array<string, int>
     */
    private array $lastUsed = [];

    private readonly FormatterInterface $formatter;

    /**
     * Create a channel manager.
     *
     * @param string $basePath Base directory for channel log files
     * @param FormatterInterface|null $formatter Formatter for log records, defaults to JsonFormatter
     * @param int $minLevel Minimum log level for created handlers
     * @param int $maxChannels Maximum number of cached handlers before LRU eviction
     */
    public function __construct(
        private readonly string $basePath,
        ?FormatterInterface $formatter = null,
        private readonly int $minLevel = 100,
        private readonly int $maxChannels = 50,
    ) {
        $this->formatter = $formatter ?? new JsonFormatter();
    }

    /**
     * Get the handler for a given channel, creating it lazily if needed.
     *
     * If the maximum number of cached handlers is reached, the least-recently-used
     * handler is evicted before creating a new one.
     *
     * @param string $channel The channel name to get a handler for
     *
     * @return HandlerInterface The handler for the channel
     */
    public function getHandler(string $channel): HandlerInterface
    {
        $key = $channel;

        if (isset($this->handlers[$key])) {
            $this->lastUsed[$key] = time();
            return $this->handlers[$key];
        }

        if (count($this->handlers) >= $this->maxChannels) {
            $this->evictLeastRecentlyUsed();
        }

        $path = rtrim($this->basePath, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $key . '.log';

        $handler = new StreamHandler($path, $this->formatter, $this->minLevel);

        $this->handlers[$key] = $handler;
        $this->lastUsed[$key] = time();

        return $handler;
    }

    /**
     * Evict the least-recently-used handler from the cache.
     *
     * @return void
     */
    private function evictLeastRecentlyUsed(): void
    {
        if ($this->lastUsed === []) {
            return;
        }

        $lruKey = array_keys($this->lastUsed, min($this->lastUsed), true)[0];

        unset($this->handlers[$lruKey], $this->lastUsed[$lruKey]);
    }
}
