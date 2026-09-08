<?php

declare(strict_types=1);

/**
 * The async seam: publish an event for a handler to run off the request
 * path. Transport is the implementer's business — a queue broker
 * (phpdot/rabbitmq), a sync fallback, anything — the contract is only the
 * hand-off: which event, which handler, which queue priority.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Contracts\Event;

interface AsyncDispatcherInterface
{
    /**
     * Publish an event to be handled asynchronously by the specified handler.
     *
     * @param object $event The event object
     * @param string $handlerClass The handler class to invoke when consuming
     * @param int $priority Queue priority (0-10, higher = more urgent)
     *
     * @return void
     */
    public function publishAsync(object $event, string $handlerClass, int $priority = 0): void;
}
