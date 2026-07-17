<?php

declare(strict_types=1);

/**
 * Fluent filter builder for MongoDB query documents.
 *
 * Compiles chained method calls into a MongoDB filter array.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\MongoDB\Filter;

final class Filter
{
    /**
     * @var array<string, mixed>
     */
    private array $conditions = [];

    /**
     * Create a new Filter builder instance.
     *
     * @return self
     */
    public static function new(): self
    {
        return new self();
    }

    /**
     * Field equals value.
     *
     * @param string $field
     * @param mixed $value
     *
     * @return Filter
     */
    public function eq(string $field, mixed $value): self
    {
        $this->addOperator($field, '$eq', $value);

        return $this;
    }

    /**
     * Field does not equal value.
     *
     * @param string $field
     * @param mixed $value
     *
     * @return Filter
     */
    public function ne(string $field, mixed $value): self
    {
        $this->addOperator($field, '$ne', $value);

        return $this;
    }

    /**
     * Field is greater than value.
     *
     * @param string $field
     * @param mixed $value
     *
     * @return Filter
     */
    public function gt(string $field, mixed $value): self
    {
        $this->addOperator($field, '$gt', $value);

        return $this;
    }

    /**
     * Field is greater than or equal to value.
     *
     * @param string $field
     * @param mixed $value
     *
     * @return Filter
     */
    public function gte(string $field, mixed $value): self
    {
        $this->addOperator($field, '$gte', $value);

        return $this;
    }

    /**
     * Field is less than value.
     *
     * @param string $field
     * @param mixed $value
     *
     * @return Filter
     */
    public function lt(string $field, mixed $value): self
    {
        $this->addOperator($field, '$lt', $value);

        return $this;
    }

    /**
     * Field is less than or equal to value.
     *
     * @param string $field
     * @param mixed $value
     *
     * @return Filter
     */
    public function lte(string $field, mixed $value): self
    {
        $this->addOperator($field, '$lte', $value);

        return $this;
    }

    /**
     * Field value is in the given array.
     *
     * @param list<mixed> $values
     * @param string $field
     *
     * @return Filter
     */
    public function in(string $field, array $values): self
    {
        $this->addOperator($field, '$in', $values);

        return $this;
    }

    /**
     * Field value is not in the given array.
     *
     * @param list<mixed> $values
     * @param string $field
     *
     * @return Filter
     */
    public function nin(string $field, array $values): self
    {
        $this->addOperator($field, '$nin', $values);

        return $this;
    }

    /**
     * Array field contains all specified values.
     *
     * @param list<mixed> $values
     * @param string $field
     *
     * @return Filter
     */
    public function all(string $field, array $values): self
    {
        $this->addOperator($field, '$all', $values);

        return $this;
    }

    /**
     * Array field has the specified size.
     *
     * @param int $size
     * @param string $field
     *
     * @return Filter
     */
    public function size(string $field, int $size): self
    {
        $this->addOperator($field, '$size', $size);

        return $this;
    }

    /**
     * Array field contains an element matching the filter.
     *
     * @param array<string, mixed> $filter
     * @param string $field
     *
     * @return Filter
     */
    public function elemMatch(string $field, array $filter): self
    {
        $this->addOperator($field, '$elemMatch', $filter);

        return $this;
    }

    /**
     * Logical OR — at least one filter must match.
     *
     * @param self $filters
     *
     * @return Filter
     */
    public function or(self ...$filters): self
    {
        $compiled = array_map(static fn(self $f): array => $f->toArray(), $filters);
        $this->conditions['$or'] = $compiled;

        return $this;
    }

    /**
     * Logical AND — all filters must match.
     *
     * @param Filter $filters
     *
     * @return Filter
     */
    public function and(self ...$filters): self
    {
        $compiled = array_map(static fn(self $f): array => $f->toArray(), $filters);
        $this->conditions['$and'] = $compiled;

        return $this;
    }

    /**
     * Logical NOR — none of the filters must match.
     *
     * @param Filter $filters
     *
     * @return Filter
     */
    public function nor(self ...$filters): self
    {
        $compiled = array_map(static fn(self $f): array => $f->toArray(), $filters);
        $this->conditions['$nor'] = $compiled;

        return $this;
    }

    /**
     * Logical NOT — negate a field condition.
     *
     * @param array<string, mixed> $expression
     * @param string $field
     *
     * @return Filter
     */
    public function not(string $field, array $expression): self
    {
        $this->addOperator($field, '$not', $expression);

        return $this;
    }

    /**
     * Field exists (or does not exist).
     *
     * @param bool $exists
     * @param string $field
     *
     * @return Filter
     */
    public function exists(string $field, bool $exists = true): self
    {
        $this->addOperator($field, '$exists', $exists);

        return $this;
    }

    /**
     * Field is of the specified BSON type.
     *
     * @param string|int $type
     * @param string $field
     *
     * @return Filter
     */
    public function type(string $field, string|int $type): self
    {
        $this->addOperator($field, '$type', $type);

        return $this;
    }

    /**
     * Field matches a regular expression.
     *
     * @param string $pattern
     * @param string $flags
     * @param string $field
     *
     * @return Filter
     */
    public function regex(string $field, string $pattern, string $flags = ''): self
    {
        $this->addOperator($field, '$regex', $pattern);
        if ($flags !== '') {
            $this->addOperator($field, '$options', $flags);
        }

        return $this;
    }

    /**
     * Full-text search.
     *
     * @param array<string, mixed> $options Additional text search options
     * @param string $search
     *
     * @return Filter
     */
    public function text(string $search, array $options = []): self
    {
        $textQuery = ['$search' => $search, ...$options];
        $this->conditions['$text'] = $textQuery;

        return $this;
    }

    /**
     * Near a point (requires 2dsphere index).
     *
     * @param array{float, float} $coordinates [longitude, latitude]
     * @param ?float $maxDistance
     * @param ?float $minDistance
     * @param string $field
     *
     * @return Filter
     */
    public function near(string $field, array $coordinates, ?float $maxDistance = null, ?float $minDistance = null): self
    {
        $query = [
            '$geometry' => [
                'type' => 'Point',
                'coordinates' => $coordinates,
            ],
        ];

        if ($maxDistance !== null) {
            $query['$maxDistance'] = $maxDistance;
        }

        if ($minDistance !== null) {
            $query['$minDistance'] = $minDistance;
        }

        $this->addOperator($field, '$near', $query);

        return $this;
    }

    /**
     * Add a raw filter condition.
     *
     * @param array<string, mixed> $filter
     *
     * @return Filter
     */
    public function raw(array $filter): self
    {
        $this->conditions = array_merge($this->conditions, $filter);

        return $this;
    }

    /**
     * Compile the filter to a MongoDB query array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->conditions;
    }

    /**
     * Add an operator condition to a field.
     *
     * @param string $operator
     * @param string $field
     * @param mixed $value
     *
     * @return void
     */
    private function addOperator(string $field, string $operator, mixed $value): void
    {
        if (!isset($this->conditions[$field]) || !is_array($this->conditions[$field])) {
            $this->conditions[$field] = [];
        }

        $this->conditions[$field][$operator] = $value;
    }
}
