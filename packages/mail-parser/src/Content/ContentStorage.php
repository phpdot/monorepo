<?php

declare(strict_types=1);

/**
 * Where an attachment's content lives once a message is parsed: decoded in memory, left in the message and decoded
 * when read, or not kept at all.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Content;

enum ContentStorage
{
    case Memory;
    case Source;
    case None;
}
