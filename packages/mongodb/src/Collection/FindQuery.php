<?php

declare(strict_types=1);

/**
 * Fluent builder for find queries.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\MongoDB\Collection;

use MongoDB\Driver\Session;
use PHPdot\Contracts\Pagination\CursorPaginator;
use PHPdot\Contracts\Pagination\Paginator;
use PHPdot\MongoDB\Document\Cursor;
use PHPdot\MongoDB\Document\Document;
use PHPdot\MongoDB\Exception\InvalidCursorException;
use PHPdot\MongoDB\Exception\QueryException;
use PHPdot\MongoDB\Filter\Filter;
use PHPdot\MongoDB\Pagination\CursorCodec;

final class FindQuery
{
    /**
     * @var array<string, mixed>
     */
    private array $filter = [];

    /**
     * @var array<string, mixed>
     */
    private array $options = [];

    /**
     * Build a find query against a collection.
     *
     * @param Collection $collection The collection to query
     */
    public function __construct(
        private readonly Collection $collection,
    ) {}

    /**
     * Set the query filter from an array.
     *
     * @param array<string, mixed> $filter
     *
     * @return self
     */
    public function filter(array $filter): self
    {
        $this->filter = $filter;

        return $this;
    }

    /**
     * Build the filter fluently via a callback.
     *
     * @param callable(Filter): Filter $callback
     *
     * @return FindQuery
     */
    public function where(callable $callback): self
    {
        $filter = Filter::new();
        $callback($filter);
        $this->filter = $filter->toArray();

        return $this;
    }

    /**
     * Fields to return. MongoDB projection syntax.
     *
     * @param array<string, int> $fields
     *
     * @return FindQuery
     */
    public function projection(array $fields): self
    {
        $this->options['projection'] = $fields;

        return $this;
    }

    /**
     * Sort order.
     *
     * @param array<string, int> $sort
     *
     * @return FindQuery
     */
    public function sort(array $sort): self
    {
        $this->options['sort'] = $sort;

        return $this;
    }

    /**
     * Maximum documents to return.
     *
     * @param int $limit
     *
     * @return FindQuery
     */
    public function limit(int $limit): self
    {
        $this->options['limit'] = $limit;

        return $this;
    }

    /**
     * Documents to skip.
     *
     * @param int $skip
     *
     * @return FindQuery
     */
    public function skip(int $skip): self
    {
        $this->options['skip'] = $skip;

        return $this;
    }

    /**
     * Index hint.
     *
     * @param string|array<string, int> $hint
     *
     * @return FindQuery
     */
    public function hint(string|array $hint): self
    {
        $this->options['hint'] = $hint;

        return $this;
    }

    /**
     * Collation for string comparison.
     *
     * @param array<string, mixed> $collation
     *
     * @return FindQuery
     */
    public function collation(array $collation): self
    {
        $this->options['collation'] = $collation;

        return $this;
    }

    /**
     * Maximum execution time in milliseconds.
     *
     * @param int $ms
     *
     * @return FindQuery
     */
    public function maxTimeMS(int $ms): self
    {
        $this->options['maxTimeMS'] = $ms;

        return $this;
    }

    /**
     * Batch size for cursor.
     *
     * @param int $size
     *
     * @return FindQuery
     */
    public function batchSize(int $size): self
    {
        $this->options['batchSize'] = $size;

        return $this;
    }

    /**
     * Allow disk use for large sorts.
     *
     * @param bool $allow
     *
     * @return FindQuery
     */
    public function allowDiskUse(bool $allow = true): self
    {
        $this->options['allowDiskUse'] = $allow;

        return $this;
    }

    /**
     * Comment for query profiler.
     *
     * @param string $comment
     *
     * @return FindQuery
     */
    public function comment(string $comment): self
    {
        $this->options['comment'] = $comment;

        return $this;
    }

    /**
     * Session for transactions.
     *
     * @param Session $session
     *
     * @return FindQuery
     */
    public function session(Session $session): self
    {
        $this->options['session'] = $session;

        return $this;
    }

    /**
     * Any additional option not covered above.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return FindQuery
     */
    public function option(string $key, mixed $value): self
    {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * Execute the query and return a Cursor of Documents.
     *
     * @return Cursor
     */
    public function execute(): Cursor
    {
        return $this->collection->executeFindQuery($this->filter, $this->options);
    }

    /**
     * Execute and return the first Document or null.
     *
     * @return ?Document
     */
    public function first(): null|Document
    {
        $options = $this->options;
        $options['limit'] = 1;

        return $this->collection->executeFindQuery($this->filter, $options)->first();
    }

    /**
     * Execute and return the count of matching documents.
     *
     * @return int
     */
    public function count(): int
    {
        return $this->collection->executeCountQuery($this->filter, $this->countOptions(withLimitAndSkip: true));
    }

    /**
     * Execute the query as an offset-paginated page with a total count.
     *
     * Appends `_id` to the sort as a tiebreaker so pages stay stable when
     * the primary sort has duplicate values.
     *
     * @param int $page The current page number (1-based)
     * @param int $perPage The number of items per page
     *
     * @return Paginator<Document>
     */
    public function paginate(int $page = 1, int $perPage = 15, bool $stableSort = false): Paginator
    {
        $this->assertPageArguments($page, $perPage);

        $total = $this->collection->executeCountQuery($this->filter, $this->countOptions());

        $options = $stableSort ? $this->stabilizeSort($this->options) : $this->options;
        $options['skip'] = ($page - 1) * $perPage;
        $options['limit'] = $perPage;

        $items = $this->collection->executeFindQuery($this->filter, $options)->toArray();

        return new Paginator($items, $total, $perPage, $page);
    }

    /**
     * Execute the query as an offset-paginated page without a total count.
     *
     * Fetches one extra document to detect whether more pages exist; the
     * paginator reports total() as -1.
     *
     * @param int $page The current page number (1-based)
     * @param int $perPage The number of items per page
     *
     * @return Paginator<Document>
     */
    public function simplePaginate(int $page = 1, int $perPage = 15, bool $stableSort = false): Paginator
    {
        $this->assertPageArguments($page, $perPage);

        $options = $stableSort ? $this->stabilizeSort($this->options) : $this->options;
        $options['skip'] = ($page - 1) * $perPage;
        $options['limit'] = $perPage + 1;

        $documents = $this->collection->executeFindQuery($this->filter, $options)->toArray();
        $hasMore = count($documents) > $perPage;

        return new Paginator(array_slice($documents, 0, $perPage), -1, $perPage, $page, $hasMore);
    }

    /**
     * Execute the query as a cursor-paginated page.
     *
     * Orders by `$field`, fetches one extra document to detect more pages,
     * and encodes the last item's field value as the opaque next cursor.
     * The field must be unique, immutable, and indexed — `_id` satisfies
     * all three.
     *
     * @param int $perPage The number of items per page
     * @param string|null $cursor The opaque cursor string from a previous page
     * @param string $field The field to paginate by
     * @param int $direction Sort direction: 1 ascending, -1 descending
     *
     * @throws InvalidCursorException If the cursor cannot be decoded
     * @throws QueryException If the field is missing from a result document
     *
     * @return CursorPaginator<Document>
     */
    public function cursorPaginate(int $perPage = 15, null|string $cursor = null, string $field = '_id', int $direction = 1): CursorPaginator
    {
        if ($perPage < 1) {
            throw new \InvalidArgumentException('Per-page must be at least 1, got ' . $perPage);
        }

        if ($direction !== 1 && $direction !== -1) {
            throw new \InvalidArgumentException('Direction must be 1 or -1, got ' . $direction);
        }

        if (isset($this->options['skip'])) {
            throw new \LogicException('cursorPaginate() cannot be combined with skip()');
        }

        $filter = $this->filter;

        if ($cursor !== null) {
            $condition = [$field => [$direction === 1 ? '$gt' : '$lt' => CursorCodec::decode($cursor)]];
            $filter = $filter === [] ? $condition : ['$and' => [$filter, $condition]];
        }

        $options = $this->options;
        $options['sort'] = [$field => $direction];
        $options['limit'] = $perPage + 1;

        $documents = $this->collection->executeFindQuery($filter, $options)->toArray();
        $hasMore = count($documents) > $perPage;
        $items = array_slice($documents, 0, $perPage);

        return new CursorPaginator(
            $items,
            $perPage,
            $this->nextCursorFrom(array_slice($items, 0, 1), $field, $cursor !== null),
            $hasMore,
            $this->nextCursorFrom($items, $field, $hasMore),
        );
    }

    /**
     * Execute the query as a cursor-paginated page ordered by a field that is
     * NOT unique.
     *
     * cursorPaginate() requires its field unique — "after this value" must name
     * exactly one resume point. A calendar field is not: many rows share one
     * instant, and a single-value cursor either skips the rest of a shared
     * group or re-reads it at every page edge. So this orders by the PAIR —
     * the field, then `_id` as the tiebreaker, the same trick paginate()'s
     * stableSort appends — and the cursor carries both values, typed, which
     * makes the resume point exact again. The bound compiles to
     *
     *     {field: {$lt: v}} OR {field: v, _id: {$lt: id}}
     *
     * which an index led by `$field` answers; the `_id` arm only ever decides
     * between rows sharing one field value.
     *
     * @param string $field The sort field — indexed and immutable; uniqueness not required
     * @param int $direction Sort direction: 1 ascending, -1 descending — applied to both keys
     * @param int $perPage The number of items per page
     * @param string|null $cursor The opaque cursor string from a previous page
     *
     * @throws InvalidCursorException If the cursor cannot be decoded, or does not hold this method's pair
     * @throws QueryException If either key is missing from a result document
     *
     * @return CursorPaginator<Document>
     */
    public function cursorPaginateBy(string $field, int $direction = 1, int $perPage = 15, null|string $cursor = null): CursorPaginator
    {
        if ($perPage < 1) {
            throw new \InvalidArgumentException('Per-page must be at least 1, got ' . $perPage);
        }

        if ($direction !== 1 && $direction !== -1) {
            throw new \InvalidArgumentException('Direction must be 1 or -1, got ' . $direction);
        }

        if (isset($this->options['skip'])) {
            throw new \LogicException('cursorPaginateBy() cannot be combined with skip()');
        }

        $filter = $this->filter;

        if ($cursor !== null) {
            [$value, $id] = self::pair(CursorCodec::decode($cursor));
            $op = $direction === 1 ? '$gt' : '$lt';
            $condition = ['$or' => [
                [$field => [$op => $value]],
                [$field => $value, '_id' => [$op => $id]],
            ]];
            $filter = $filter === [] ? $condition : ['$and' => [$filter, $condition]];
        }

        $options = $this->options;
        $options['sort'] = [$field => $direction, '_id' => $direction];
        $options['limit'] = $perPage + 1;

        $documents = $this->collection->executeFindQuery($filter, $options)->toArray();
        $hasMore = count($documents) > $perPage;
        $items = array_slice($documents, 0, $perPage);

        return new CursorPaginator(
            $items,
            $perPage,
            $this->pairCursorFrom(array_slice($items, 0, 1), $field, $cursor !== null),
            $hasMore,
            $this->pairCursorFrom($items, $field, $hasMore),
        );
    }

    /**
     * Get the compiled filter array (for debugging).
     *
     * @return array<string, mixed>
     */
    public function getFilter(): array
    {
        return $this->filter;
    }

    /**
     * Get the compiled options array (for debugging).
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Explain the query execution plan.
     *
     * @return array<string, mixed>
     */
    public function explain(): array
    {
        return $this->collection->executeFindExplain($this->filter, $this->options);
    }

    /**
     * Guard 1-based pagination arguments.
     *
     * @param int $page
     * @param int $perPage
     *
     * @return void
     */
    private function assertPageArguments(int $page, int $perPage): void
    {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be at least 1, got ' . $page);
        }

        if ($perPage < 1) {
            throw new \InvalidArgumentException('Per-page must be at least 1, got ' . $perPage);
        }
    }

    /**
     * Append `_id` to the sort so offset pages stay deterministic.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function stabilizeSort(array $options): array
    {
        $sort = $options['sort'] ?? [];

        if (is_array($sort) && !array_key_exists('_id', $sort)) {
            $sort['_id'] = 1;
            $options['sort'] = $sort;
        }

        return $options;
    }

    /**
     * Keep only the options countDocuments understands.
     *
     * @param bool $withLimitAndSkip Include limit/skip (count() semantics) or drop them (paginate() totals)
     *
     * @return array<string, mixed>
     */
    private function countOptions(bool $withLimitAndSkip = false): array
    {
        $allowed = [
            'collation' => true,
            'comment' => true,
            'hint' => true,
            'maxTimeMS' => true,
            'readConcern' => true,
            'readPreference' => true,
            'session' => true,
        ];

        if ($withLimitAndSkip) {
            $allowed += ['limit' => true, 'skip' => true];
        }

        return array_intersect_key($this->options, $allowed);
    }

    /**
     * Encode the next-page cursor from the last item of the current page.
     *
     * @param list<Document> $items
     * @param string $field
     * @param bool $hasMore
     *
     * @throws QueryException If the field is missing from the last document
     *
     * @return ?string
     */
    private function nextCursorFrom(array $items, string $field, bool $hasMore): null|string
    {
        if (!$hasMore || $items === []) {
            return null;
        }

        $raw = $items[count($items) - 1]->getRaw();

        if (!array_key_exists($field, $raw)) {
            throw new QueryException(
                "Cursor field '{$field}' missing from result document — is it excluded by the projection?",
                'cursorPaginate',
                $this->collection->getName(),
            );
        }

        return CursorCodec::encode($raw[$field]);
    }

    /**
     * The decoded pair a compound cursor must hold.
     *
     * @param mixed $decoded What the codec produced
     *
     * @throws InvalidCursorException If it is not a two-value list
     *
     * @return array{mixed, mixed}
     */
    private static function pair(mixed $decoded): array
    {
        if (!is_array($decoded) || !array_is_list($decoded) || count($decoded) !== 2) {
            throw new InvalidCursorException('Malformed pagination cursor: expected a field/_id pair');
        }

        return [$decoded[0], $decoded[1]];
    }

    /**
     * Build the compound cursor off a page edge, or null when there is no edge.
     *
     * The mirror of nextCursorFrom() for the two-key mode: both the field and
     * `_id` ride in one encoded pair, typed, so the resume point survives the
     * round trip exactly.
     *
     * @param array<int, Document> $items
     * @param string $field
     * @param bool $hasMore
     *
     * @throws QueryException If either key is missing from the edge document
     *
     * @return ?string
     */
    private function pairCursorFrom(array $items, string $field, bool $hasMore): null|string
    {
        if (!$hasMore || $items === []) {
            return null;
        }

        $raw = $items[count($items) - 1]->getRaw();

        if (!array_key_exists($field, $raw) || !array_key_exists('_id', $raw)) {
            throw new QueryException(
                "Cursor fields '{$field}' and '_id' must both be in the result document — excluded by the projection?",
                'cursorPaginateBy',
                $this->collection->getName(),
            );
        }

        return CursorCodec::encode([$raw[$field], $raw['_id']]);
    }
}
