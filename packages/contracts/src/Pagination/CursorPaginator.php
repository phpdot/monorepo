<?php

declare(strict_types=1);

/**
 * Cursor-based paginator for efficient pagination of large result sets.
 *
 * A CARRIER with public properties and nothing else: json_encode() reads it
 * as-is, so the property names ARE the wire names.
 *
 * Uses an opaque cursor string instead of page numbers, enabling consistent
 * pagination even when rows are inserted or deleted. Storage-agnostic: each
 * driver encodes its own cursor format (the SQL builder base64-encodes a
 * column value, the MongoDB builder wraps a typed BSON value in extended
 * JSON) — this object never inspects the strings. Builders must supply
 * `next_cursor` whenever `has_more` is true; only the builder knows which
 * field the cursor tracks and how to encode it.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Contracts\Pagination;

/**
 * The second docblock is not an oversight. This file's prose sits above the
 * namespace, as every file here does, but `@template` only binds when it is
 * attached to the class declaration itself — above the namespace it is a file
 * annotation and the class stays non-generic.
 *
 * @template T
 */
final readonly class CursorPaginator
{
    /**
     * Assemble a cursor-paginated view over a page of items.
     *
     * @param list<T> $items The items for the current page
     * @param int $per_page The number of items per page
     * @param ?string $previous_cursor The cursor that produced this page, or null for the first page
     * @param bool $has_more Whether there are more items after this page
     * @param ?string $next_cursor The cursor for the next page, or null on the last page
     */
    public function __construct(
        public array $items,
        public int $per_page,
        public null|string $previous_cursor,
        public bool $has_more,
        public null|string $next_cursor = null,
    ) {}
}
