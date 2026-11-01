<?php

declare(strict_types=1);

/**
 * The message could not be read into a place the parser reads it from.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Exception;

final class SourceException extends ParseException
{
    public static function unwritable(): self
    {
        return new self('The message could not be copied into its temporary spool');
    }
}
