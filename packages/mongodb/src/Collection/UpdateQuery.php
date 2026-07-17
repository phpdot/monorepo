<?php

declare(strict_types=1);

/**
 * Fluent builder for update queries.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\MongoDB\Collection;

use MongoDB\Driver\Session;
use MongoDB\UpdateResult;
use PHPdot\MongoDB\Filter\Filter;

final class UpdateQuery
{
    /**
     * @var array<string, mixed>
     */
    private array $filter = [];

    /**
     * @var array<string, mixed>
     */
    private array $update = [];

    /**
     * @var array<string, mixed>
     */
    private array $options = [];

    /**
     * Build an update operation against a collection (single or many documents).
     *
     * @param Collection $collection The collection to update
     * @param bool $many Whether to update many documents
     */
    public function __construct(
        private readonly Collection $collection,
        private readonly bool $many,
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
     * @return UpdateQuery
     */
    public function where(callable $callback): self
    {
        $filter = Filter::new();
        $callback($filter);
        $this->filter = $filter->toArray();

        return $this;
    }

    /**
     * Set the update document. Accepts raw MongoDB update operators.
     *
     * @param array<string, mixed> $update
     *
     * @return UpdateQuery
     */
    public function update(array $update): self
    {
        $this->update = $update;

        return $this;
    }

    /**
     * Enable upsert — insert if no match found.
     *
     * @param bool $upsert
     *
     * @return UpdateQuery
     */
    public function upsert(bool $upsert = true): self
    {
        $this->options['upsert'] = $upsert;

        return $this;
    }

    /**
     * Array filters for positional operators.
     *
     * @param list<array<string, mixed>> $filters
     *
     * @return UpdateQuery
     */
    public function arrayFilters(array $filters): self
    {
        $this->options['arrayFilters'] = $filters;

        return $this;
    }

    /**
     * Index hint.
     *
     * @param string|array<string, int> $hint
     *
     * @return UpdateQuery
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
     * @return UpdateQuery
     */
    public function collation(array $collation): self
    {
        $this->options['collation'] = $collation;

        return $this;
    }

    /**
     * Session for transactions.
     *
     * @param Session $session
     *
     * @return UpdateQuery
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
     * @return UpdateQuery
     */
    public function option(string $key, mixed $value): self
    {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * Execute the update query.
     *
     * @return UpdateResult
     */
    public function execute(): UpdateResult
    {
        return $this->collection->executeUpdateQuery($this->filter, $this->update, $this->options, $this->many);
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
     * Get the compiled update array (for debugging).
     *
     * @return array<string, mixed>
     */
    public function getUpdate(): array
    {
        return $this->update;
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
}
