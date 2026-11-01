<?php

declare(strict_types=1);

/**
 * A parsed result was written to; every result of the parser is read-only.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Exception;

final class ImmutableResultException extends ParseException
{
    public static function of(string $result): self
    {
        return new self(sprintf('%s is immutable', $result));
    }
}
