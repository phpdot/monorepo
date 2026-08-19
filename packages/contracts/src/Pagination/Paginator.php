<?php

declare(strict_types=1);

/**
 * Offset-based paginator for paged result sets.
 *
 * A CARRIER with public properties and nothing else: json_encode() reads it
 * as-is, so the property names ARE the wire names. Storage-agnostic:
 * `phpdot/database` pages SQL rows with it, `phpdot/mongodb` pages Documents,
 * a service pages the DTOs it mapped them to — all emitting the same shape.
 *
 * The two derived facts are assigned ONCE, at construction: `last_page` from
 * the total, and `has_more` from the page position unless the builder states
 * it — simple pagination knows more rows exist without knowing how many, and
 * passes the flag with `total` -1.
 *
 * Generic in what it carries. A builder fills one with raw rows or documents,
 * but a service that maps those to DTOs pages the DTOs — and without
 * `@template` that second paginator would be lying about its contents, since
 * `items` would promise arrays to everything downstream.
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
final readonly class Paginator
{
    /* Declared here rather than promoted, ALL of them: json_encode() emits
       properties in declaration order, and promoted ones trail the declared —
       so mixing the two puts the derived pair before the items. */

    /** @var list<T> The items for the current page. */
    public array $items;

    /** The total number of items across all pages, -1 when unknown. */
    public int $total;

    /** The number of items per page. */
    public int $per_page;

    /** The current page number, 1-based. */
    public int $current_page;

    /** The last page number, at least 1. */
    public int $last_page;

    /** Whether more items follow the current page. */
    public bool $has_more;

    /**
     * Assemble an offset-paginated view over a page of items.
     *
     * @param list<T> $items The items for the current page
     * @param int $total The total number of items across all pages (-1 when unknown, e.g. simple pagination)
     * @param int $per_page The number of items per page
     * @param int $current_page The current page number (1-based)
     * @param bool|null $hasMore Explicit "has more pages" flag; null derives it from the total
     */
    public function __construct(array $items, int $total, int $per_page, int $current_page, null|bool $hasMore = null)
    {
        $this->items = $items;
        $this->total = $total;
        $this->per_page = $per_page;
        $this->current_page = $current_page;
        $this->last_page = $per_page <= 0 ? 1 : max(1, (int) ceil($total / $per_page));
        $this->has_more = $hasMore ?? $current_page < $this->last_page;
    }
}
