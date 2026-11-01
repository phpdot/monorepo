<?php

declare(strict_types=1);

/**
 * Server event for SMTP RCPT TO command.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Server\Event;

class RcptToEvent extends Event
{
    public function __construct(
        public readonly string $recipient,
    ) {}
}
