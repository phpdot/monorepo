<?php

declare(strict_types=1);

/**
 * Immutable collection of email addresses and groups with iteration, counting, array access,
 * and convenience filtering.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Result;

use ArrayAccess;
use ArrayIterator;
use BadMethodCallException;
use Countable;
use IteratorAggregate;

/**
 * @implements IteratorAggregate<int, Address|AddressGroup>
 * @implements ArrayAccess<int, Address|AddressGroup>
 */
final readonly class AddressList implements IteratorAggregate, Countable, ArrayAccess
{
    /** @param list<Address|AddressGroup> $items */
    public function __construct(
        private array $items = [],
    ) {}

    public function first(): Address|AddressGroup|null
    {
        return $this->items[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * Flatten all addresses, expanding groups into individual addresses.
     *
     * @return list<Address>
     */
    public function allAddresses(): array
    {
        $result = [];

        foreach ($this->items as $item) {
            if ($item instanceof Address) {
                $result[] = $item;
            } elseif ($item instanceof AddressGroup) {
                foreach ($item->addresses as $addr) {
                    $result[] = $addr;
                }
            }
        }

        return $result;
    }

    /** @return list<Address|AddressGroup> */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * @return ArrayIterator<int, Address|AddressGroup>
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
    public function offsetGet(mixed $offset): Address|AddressGroup|null
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new BadMethodCallException('AddressList is immutable');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new BadMethodCallException('AddressList is immutable');
    }
}
