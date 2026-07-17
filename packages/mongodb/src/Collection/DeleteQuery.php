<?php

declare(strict_types=1);

/**
 * Fluent builder for delete queries.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\MongoDB\Collection;

use MongoDB\DeleteResult;
use MongoDB\Driver\Session;
use PHPdot\MongoDB\Filter\Filter;

final class DeleteQuery
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
     * Build a delete operation against a collection (single or many documents).
     *
     * @param Collection $collection The collection to delete from
     * @param bool $many Whether to delete many documents
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
     * @return DeleteQuery
     */
    public function where(callable $callback): self
    {
        $filter = Filter::new();
        $callback($filter);
        $this->filter = $filter->toArray();

        return $this;
    }

    /**
     * Index hint.
     *
     * @param string|array<string, int> $hint
     *
     * @return DeleteQuery
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
     * @return DeleteQuery
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
     * @return DeleteQuery
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
     * @return DeleteQuery
     */
    public function option(string $key, mixed $value): self
    {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * Execute the delete query.
     *
     * @return DeleteResult
     */
    public function execute(): DeleteResult
    {
        return $this->collection->executeDeleteQuery($this->filter, $this->options, $this->many);
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
}
