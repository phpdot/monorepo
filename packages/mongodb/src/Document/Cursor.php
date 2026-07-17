<?php

declare(strict_types=1);

/**
 * Wraps a MongoDB cursor to yield Document instances.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\MongoDB\Document;

use Generator;
use IteratorAggregate;
use MongoDB\Driver\CursorInterface;

/**
 * @implements IteratorAggregate<int, Document>
 */
final class Cursor implements IteratorAggregate
{
    /**
     * Wrap a driver cursor to yield Document instances lazily.
     *
     * @param CursorInterface $cursor The underlying MongoDB cursor
     */
    public function __construct(
        private readonly CursorInterface $cursor,
    ) {}

    /**
     * Iterate over the cursor, yielding Document instances.
     *
     * @return Generator<int, Document>
     */
    public function getIterator(): Generator
    {
        $index = 0;
        foreach ($this->cursor as $document) {
            if ($document === null) {
                continue;
            }
            yield $index++ => Document::fromBSON($document);
        }
    }

    /**
     * Get all documents as an array.
     *
     * @return list<Document>
     */
    public function toArray(): array
    {
        return iterator_to_array($this->getIterator(), false);
    }

    /**
     * Get the first document or null if the cursor is empty.
     *
     * @return ?Document
     */
    public function first(): ?Document
    {
        foreach ($this->getIterator() as $document) {
            return $document;
        }

        return null;
    }

    /**
     * Lazily iterate the cursor as a generator.
     *
     * @return Generator<int, Document>
     */
    public function lazy(): Generator
    {
        yield from $this->getIterator();
    }

    /**
     * Count the number of documents in the cursor.
     *
     * Note: This consumes the cursor.
     *
     * @return int
     */
    public function count(): int
    {
        $count = 0;
        foreach ($this->cursor as $_) {
            $count++;
        }

        return $count;
    }
}
