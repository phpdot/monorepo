<?php

declare(strict_types=1);

/**
 * Message queue states for delivery tracking.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\DataType\Enum;

enum QueueState: string
{
    case Pending = 'pending';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
}
