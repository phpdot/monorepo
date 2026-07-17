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
use PHPdot\MongoDB\Document\Cursor;
use PHPdot\MongoDB\Document\Document;
use PHPdot\MongoDB\Filter\Filter;

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
    public function first(): ?Document
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
        return $this->collection->executeCountQuery($this->filter, $this->options);
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
}
