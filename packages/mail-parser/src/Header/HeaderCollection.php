<?php

declare(strict_types=1);

/**
 * Immutable, case-insensitive collection of parsed email headers with support for multi-value header lookup.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Header;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * @implements IteratorAggregate<int, Header>
 */
final class HeaderCollection implements IteratorAggregate, Countable
{
    /** @var array<string, list<Header>> Case-insensitive index */
    private array $index = [];

    /** @param list<Header> $headers */
    public function __construct(
        private readonly array $headers = [],
    ) {
        foreach ($this->headers as $header) {
            $this->index[strtolower($header->name)][] = $header;
        }
    }

    public function get(string $name): Header|null
    {
        $lower = strtolower($name);

        return $this->index[$lower][0] ?? null;
    }

    /** @return list<Header> */
    public function getAll(string $name): array
    {
        return $this->index[strtolower($name)] ?? [];
    }

    /** @return list<Header> */
    public function all(): array
    {
        return $this->headers;
    }

    public function has(string $name): bool
    {
        return isset($this->index[strtolower($name)]);
    }

    /**
     * @return ArrayIterator<int, Header>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->headers);
    }

    public function count(): int
    {
        return count($this->headers);
    }
}
