<?php

declare(strict_types=1);

/**
 * Server event for new SMTP connections.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Server\Event;

class ConnectEvent extends Event
{
    public function __construct(
        public readonly string $clientIp,
    ) {}
}
