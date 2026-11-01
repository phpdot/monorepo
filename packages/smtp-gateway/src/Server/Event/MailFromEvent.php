<?php

declare(strict_types=1);

/**
 * Server event for SMTP MAIL FROM command.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Server\Event;

class MailFromEvent extends Event
{
    public function __construct(
        public readonly string $sender,
        public readonly int|null $size = null,
        public readonly string|null $bodyType = null,
    ) {}
}
