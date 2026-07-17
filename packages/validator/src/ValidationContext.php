<?php

declare(strict_types=1);

/**
 * Per-rule view into the payload being validated.
 *
 * Carries the field currently under validation plus the entire data array.
 * Cross-field rules read other fields via `value()`; rules that accept a
 * literal-or-field-name reference use `dereference()`.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator;

final readonly class ValidationContext
{
    private const MISSING = "\0\0__phpdot_validator_missing__\0\0";

    /**
     * The field currently being validated and the full input data around it.
     *
     * @param string $field Name of the field under validation
     * @param array<string, mixed> $data The complete input data set
     */
    public function __construct(
        public string $field,
        public array $data,
    ) {}

    /**
     * The name of the field currently being validated.
     *
     * @return string
     */
    public function field(): string
    {
        return $this->field;
    }

    /**
     * Read another field's value from the payload. Supports dot notation.
     *
     * Returns `$default` if the field is absent.
     *
     * @param string $field
     * @param mixed $default
     *
     * @return mixed
     */
    public function value(string $field, mixed $default = null): mixed
    {
        $value = $this->walk($field);

        return $value === self::MISSING ? $default : $value;
    }

    /**
     * Whether the given field is present in the payload (dot notation supported).
     *
     * @param string $field
     *
     * @return bool
     */
    public function has(string $field): bool
    {
        return $this->walk($field) !== self::MISSING;
    }

    /**
     * Resolve a literal-or-field reference.
     *
     * If `$reference` is a string that names a field in the payload, returns
     * that field's value. Otherwise returns `$reference` unchanged. Used by
     * cross-field rules (`Gt`, `After`, `DateBetween`, …) that accept either
     * a literal or another field's name.
     *
     * @param mixed $reference
     *
     * @return mixed
     */
    public function dereference(mixed $reference): mixed
    {
        if (is_string($reference)) {
            $value = $this->walk($reference);

            if ($value !== self::MISSING) {
                return $value;
            }
        }

        return $reference;
    }

    /**
     * The complete input data set under validation.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Walk a (possibly dotted) field path. Returns the unique sentinel
     * `self::MISSING` if any segment is absent.
     *
     * @param string $field
     *
     * @return mixed
     */
    private function walk(string $field): mixed
    {
        if (array_key_exists($field, $this->data)) {
            return $this->data[$field];
        }

        if (!str_contains($field, '.')) {
            return self::MISSING;
        }

        $current = $this->data;

        foreach (explode('.', $field) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return self::MISSING;
            }

            $current = $current[$segment];
        }

        return $current;
    }
}
