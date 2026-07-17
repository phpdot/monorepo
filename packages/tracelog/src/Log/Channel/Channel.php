<?php

declare(strict_types=1);

/**
 * Channel
 *
 * Default log channels for common application concerns.
 * Each channel maps to a separate log file for targeted I/O distribution.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Log\Channel;

enum Channel: string
{
    case App      = 'app';
    case Auth     = 'auth';
    case Database = 'database';
    case Http     = 'http';
    case Queue    = 'queue';
    case Mail     = 'mail';
    case Cache    = 'cache';
    case Security = 'security';
}
