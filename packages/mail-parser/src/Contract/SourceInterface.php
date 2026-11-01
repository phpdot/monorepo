<?php

declare(strict_types=1);

/**
 * The bytes of a message with CRLF line endings throughout, read by offset so a part is copied only when it is kept.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Contract;

interface SourceInterface
{
    public function length(): int;

    /**
     * The bytes from `$offset`, at most `$length` of them and none past the end.
     */
    public function read(int $offset, int $length): string;

    /**
     * Where `$needle` first starts at or after `$from` and ends by `$to`, or null.
     */
    public function find(string $needle, int $from, int $to): int|null;
}
