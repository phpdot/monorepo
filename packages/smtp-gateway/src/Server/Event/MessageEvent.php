<?php

declare(strict_types=1);

/**
 * Server event fired when a complete message has been received (DATA complete).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Server\Event;

class MessageEvent extends Event
{
    /**
     * @param list<string> $recipients
     */
    public function __construct(
        public readonly string $id,
        public readonly string $sender,
        public readonly array $recipients,
        public readonly string $message,
        public readonly int $size,
        public readonly string $authUser,
        public readonly string $clientIp,
    ) {}
}
