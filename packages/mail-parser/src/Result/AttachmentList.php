<?php

declare(strict_types=1);

/**
 * Immutable collection of email attachments with filtering for inline and regular attachments.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Result;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use PHPdot\Mail\Parser\Exception\ImmutableResultException;

/**
 * @implements IteratorAggregate<int, Attachment>
 * @implements ArrayAccess<int, Attachment>
 */
final readonly class AttachmentList implements IteratorAggregate, Countable, ArrayAccess
{
    /** @param list<Attachment> $items */
    public function __construct(
        private array $items = [],
    ) {}

    public function first(): Attachment|null
    {
        return $this->items[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * @return list<Attachment>
     */
    public function inline(): array
    {
        return array_values(array_filter(
            $this->items,
            fn(Attachment $a) => $a->disposition === 'inline',
        ));
    }

    /**
     * @return list<Attachment>
     */
    public function regular(): array
    {
        return array_values(array_filter(
            $this->items,
            fn(Attachment $a) => $a->disposition === 'attachment',
        ));
    }

    public function get(int $index): Attachment|null
    {
        return $this->items[$index] ?? null;
    }

    /** @return list<Attachment> */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * @return ArrayIterator<int, Attachment>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @param int $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * @param int $offset
     */
    public function offsetGet(mixed $offset): Attachment|null
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw ImmutableResultException::of('AttachmentList');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw ImmutableResultException::of('AttachmentList');
    }
}
