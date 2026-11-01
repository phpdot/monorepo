<?php

declare(strict_types=1);

/**
 * A stretch of another source read as a message of its own, as a forwarded message is read from inside its parent.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Source;

use PHPdot\Mail\Parser\Contract\SourceInterface;

final readonly class SliceSource implements SourceInterface
{
    public function __construct(
        private SourceInterface $source,
        private int $start,
        private int $end,
    ) {}

    public function length(): int
    {
        return $this->end - $this->start;
    }

    public function read(int $offset, int $length): string
    {
        return $this->source->read($this->start + $offset, min($length, $this->end - $this->start - $offset));
    }

    public function find(string $needle, int $from, int $to): int|null
    {
        $found = $this->source->find($needle, $this->start + $from, min($this->end, $this->start + $to));

        return $found === null ? null : $found - $this->start;
    }
}
