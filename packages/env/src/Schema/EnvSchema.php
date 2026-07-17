<?php

declare(strict_types=1);

/**
 * EnvSchema
 *
 * Schema loader, validator, and type caster for environment variables.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Env\Schema;

use BackedEnum;
use JsonException;
use PHPdot\Env\Enum\EnvType;
use PHPdot\Env\Exception\SchemaException;
use PHPdot\Env\Exception\ValidationException;

final class EnvSchema
{
    /**
     * @var array<string, Definition>
     */
    private readonly array $definitions;

    /**
     * Load and normalize a schema from a file path or an inline array.
     *
     * @param string|array<string, array<string, mixed>> $schema File path or inline array.
     *
     * @throws SchemaException On invalid definitions.
     */
    public function __construct(string|array $schema)
    {
        if (is_string($schema)) {
            if (!is_file($schema)) {
                throw SchemaException::invalidDefinition('*', "Schema file not found: {$schema}");
            }
            $loaded = require $schema;
            if (!is_array($loaded)) {
                throw SchemaException::invalidDefinition('*', 'Schema file must return an array');
            }
            /**
             * @var array<string, mixed> $raw
             */
            $raw = $loaded;
        } else {
            $raw = $schema;
        }

        $definitions = [];
        foreach ($raw as $key => $def) {
            /**
             * @var array<string, mixed> $normalized
             */
            $normalized = is_array($def) ? $def : [];
            $definitions[$key] = $this->normalize($key, $normalized);
        }
        $this->definitions = $definitions;
    }

    /**
     * Checks if a key exists in the schema.
     *
     * @param string $key The environment variable name.
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    /**
     * Returns the definition for a given key.
     *
     * @param string $key The environment variable name.
     *
     * @throws SchemaException If the key is not defined in the schema.
     *
     * @return Definition
     */
    public function getDefinition(string $key): Definition
    {
        if (!isset($this->definitions[$key])) {
            throw SchemaException::unknownKey($key);
        }

        return $this->definitions[$key];
    }

    /**
     * Returns the type for a given key.
     *
     * @param string $key The environment variable name.
     *
     * @throws SchemaException If the key is not defined in the schema.
     *
     * @return EnvType
     */
    public function getType(string $key): EnvType
    {
        return $this->getDefinition($key)->type;
    }

    /**
     * Returns the default value for a given key.
     *
     * @param string $key The environment variable name.
     *
     * @throws SchemaException If the key is not defined in the schema.
     *
     * @return mixed
     */
    public function getDefault(string $key): mixed
    {
        return $this->getDefinition($key)->default;
    }

    /**
     * Checks if a key is required.
     *
     * @param string $key The environment variable name.
     *
     * @throws SchemaException If the key is not defined in the schema.
     *
     * @return bool
     */
    public function isRequired(string $key): bool
    {
        return $this->getDefinition($key)->required;
    }

    /**
     * Checks if a key is marked as sensitive.
     *
     * @param string $key The environment variable name.
     *
     * @throws SchemaException If the key is not defined in the schema.
     *
     * @return bool
     */
    public function isSensitive(string $key): bool
    {
        return $this->getDefinition($key)->sensitive;
    }

    /**
     * Returns all keys defined in the schema.
     *
     * @return list<string>
     */
    public function getKeys(): array
    {
        return array_keys($this->definitions);
    }

    /**
     * Casts a raw string value to its typed representation based on the schema.
     *
     * @param string $key The environment variable name.
     * @param string|null $raw The raw string value from the env file.
     *
     * @throws SchemaException If the key is not defined in the schema.
     * @throws ValidationException If the value cannot be cast or is empty when notEmpty is required.
     *
     * @return mixed The typed value.
     */
    public function castValue(string $key, ?string $raw): mixed
    {
        $definition = $this->getDefinition($key);

        if ($raw === null) {
            return $definition->default;
        }

        if ($definition->notEmpty && trim($raw) === '') {
            throw ValidationException::constraintFailed($key, 'value must not be empty');
        }

        return match ($definition->type) {
            EnvType::STRING => $raw,
            EnvType::INT    => $this->castToInt($key, $raw),
            EnvType::FLOAT  => $this->castToFloat($key, $raw),
            EnvType::BOOL   => $this->castToBool($key, $raw),
            EnvType::ENUM   => $this->castToEnum($key, $raw, $definition->enum ?? ''),
            EnvType::LIST   => $this->castToList($raw, $definition->separator),
            EnvType::JSON   => $this->castToJson($key, $raw),
        };
    }

    /**
     * Validates constraints on an already-typed value.
     *
     * @param string $key The environment variable name.
     * @param mixed $typedValue The typed value to validate.
     *
     * @throws SchemaException If the key is not defined in the schema.
     * @throws ValidationException If a constraint is violated.
     *
     * @return void
     */
    public function validateConstraints(string $key, mixed $typedValue): void
    {
        $definition = $this->getDefinition($key);

        if (($definition->min !== null) && is_numeric($typedValue) && $typedValue < $definition->min) {
            throw ValidationException::constraintFailed(
                $key,
                "value must be at least {$definition->min}",
            );
        }

        if (($definition->max !== null) && is_numeric($typedValue) && $typedValue > $definition->max) {
            throw ValidationException::constraintFailed(
                $key,
                "value must be at most {$definition->max}",
            );
        }

        if ($definition->allowed !== null && is_string($typedValue) && !in_array($typedValue, $definition->allowed, true)) {
            $allowedList = implode(', ', $definition->allowed);
            throw ValidationException::constraintFailed(
                $key,
                "value must be one of: {$allowedList}",
            );
        }

        if ($definition->pattern !== null && is_string($typedValue) && preg_match($definition->pattern, $typedValue) !== 1) {
            throw ValidationException::constraintFailed(
                $key,
                "value must match pattern: {$definition->pattern}",
            );
        }
    }

    /**
     * Checks if a raw value is valid for the given key without throwing.
     *
     * @param string $key The environment variable name.
     * @param string|null $raw The raw string value to validate.
     *
     * @return bool True if the raw value is valid for the type.
     */
    public function validateRaw(string $key, ?string $raw): bool
    {
        try {
            $typed = $this->castValue($key, $raw);
            $this->validateConstraints($key, $typed);

            return true;
        } catch (ValidationException | SchemaException) {
            return false;
        }
    }

    /**
     * Converts a typed value back to a string for writing to an env file.
     *
     * @param string $key The environment variable name.
     * @param mixed $value The typed value to serialize.
     *
     * @throws SchemaException If the key is not defined in the schema.
     *
     * @return string The serialized string representation.
     */
    public function serializeValue(string $key, mixed $value): string
    {
        $definition = $this->getDefinition($key);

        if ($definition->type === EnvType::BOOL) {
            return ((bool) $value) ? 'true' : 'false';
        }

        if ($definition->type === EnvType::ENUM && $value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($definition->type === EnvType::LIST && is_array($value)) {
            /**
             * @var list<string> $listValue
             */
            $listValue = $value;

            return implode($definition->separator, $listValue);
        }

        if ($definition->type === EnvType::JSON) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '';
    }

    /**
     * Normalizes a raw schema array into a Definition value object.
     *
     * @param string $key The environment variable name.
     * @param array<string, mixed> $raw The raw schema definition array.
     *
     * @throws SchemaException If the definition is invalid.
     *
     * @return Definition
     */
    private function normalize(string $key, array $raw): Definition
    {
        $type = $raw['type'] ?? null;
        $enum = $raw['enum'] ?? null;

        if ($enum !== null && $type === null) {
            $type = EnvType::ENUM;
        }

        if ($type === null) {
            $type = EnvType::STRING;
        }

        if (is_string($type)) {
            $resolved = EnvType::tryFrom($type);
            if ($resolved === null) {
                throw SchemaException::invalidDefinition($key, "unknown type '{$type}'");
            }
            $type = $resolved;
        }

        if (!$type instanceof EnvType) {
            throw SchemaException::invalidDefinition($key, 'type must be a string or EnvType enum');
        }

        if ($type === EnvType::ENUM) {
            if (!is_string($enum) || $enum === '') {
                throw SchemaException::invalidDefinition($key, 'enum class must be specified for ENUM type');
            }
            if (!class_exists($enum)) {
                throw SchemaException::invalidDefinition($key, "enum class '{$enum}' does not exist");
            }
            if (!is_subclass_of($enum, BackedEnum::class)) {
                throw SchemaException::invalidDefinition($key, "enum class '{$enum}' must implement BackedEnum");
            }
        }

        /**
         * @var list<string>|null $allowed
         */
        $allowed = isset($raw['allowed']) && is_array($raw['allowed']) ? array_values($raw['allowed']) : null;

        /**
         * @var class-string<BackedEnum>|null $enumClass
         */
        $enumClass = is_string($enum) ? $enum : null;

        return new Definition(
            type: $type,
            enum: $enumClass,
            required: (bool) ($raw['required'] ?? false),
            notEmpty: (bool) ($raw['not_empty'] ?? $raw['notEmpty'] ?? false),
            default: $raw['default'] ?? null,
            description: isset($raw['description']) && is_string($raw['description']) ? $raw['description'] : null,
            separator: isset($raw['separator']) && is_string($raw['separator']) && $raw['separator'] !== '' ? $raw['separator'] : ',',
            min: isset($raw['min']) && (is_int($raw['min']) || is_float($raw['min'])) ? $raw['min'] : null,
            max: isset($raw['max']) && (is_int($raw['max']) || is_float($raw['max'])) ? $raw['max'] : null,
            allowed: $allowed,
            pattern: isset($raw['pattern']) && is_string($raw['pattern']) ? $raw['pattern'] : null,
            sensitive: (bool) ($raw['sensitive'] ?? false),
        );
    }

    /**
     * Casts a raw string to an integer.
     *
     * @param string $key The environment variable name.
     * @param string $raw The raw string value.
     *
     * @throws ValidationException If the value is not a valid integer.
     *
     * @return int
     */
    private function castToInt(string $key, string $raw): int
    {
        if (!is_numeric($raw) || str_contains($raw, '.')) {
            throw ValidationException::typeMismatch($key, 'integer', $raw);
        }

        return (int) $raw;
    }

    /**
     * Casts a raw string to a float.
     *
     * @param string $key The environment variable name.
     * @param string $raw The raw string value.
     *
     * @throws ValidationException If the value is not numeric.
     *
     * @return float
     */
    private function castToFloat(string $key, string $raw): float
    {
        if (!is_numeric($raw)) {
            throw ValidationException::typeMismatch($key, 'float', $raw);
        }

        return (float) $raw;
    }

    /**
     * Casts a raw string to a boolean.
     *
     * @param string $key The environment variable name.
     * @param string $raw The raw string value.
     *
     * @throws ValidationException If the value is not a recognized boolean string.
     *
     * @return bool
     */
    private function castToBool(string $key, string $raw): bool
    {
        $normalized = strtolower(trim($raw));

        if (in_array($normalized, ['true', '1', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['false', '0', 'no', 'off'], true)) {
            return false;
        }

        throw ValidationException::typeMismatch($key, 'boolean (true/false/1/0/yes/no/on/off)', $raw);
    }

    /**
     * Casts a raw string to a BackedEnum instance.
     *
     * @param string $key The environment variable name.
     * @param string $raw The raw string value.
     * @param string $enumClass The fully-qualified enum class name.
     *
     * @throws ValidationException If the value does not match any enum case.
     *
     * @return BackedEnum
     */
    private function castToEnum(string $key, string $raw, string $enumClass): BackedEnum
    {
        if ($enumClass === '' || !is_subclass_of($enumClass, BackedEnum::class)) {
            throw ValidationException::typeMismatch($key, 'valid enum', $raw);
        }

        /**
         * @var BackedEnum|null $result
         */
        $result = $enumClass::tryFrom($raw);

        if ($result === null) {
            /**
             * @var list<BackedEnum> $cases
             */
            $cases = $enumClass::cases();
            $valid = implode(', ', array_map(
                static fn(BackedEnum $case): string => (string) $case->value,
                $cases,
            ));
            throw ValidationException::typeMismatch($key, "one of: {$valid}", $raw);
        }

        return $result;
    }

    /**
     * Casts a raw string to a list of strings.
     *
     * @param string $raw The raw string value.
     * @param non-empty-string $separator The list separator.
     *
     * @return list<string>
     */
    private function castToList(string $raw, string $separator): array
    {
        $items = explode($separator, $raw);
        $trimmed = array_map(trim(...), $items);

        return array_values(array_filter($trimmed, static fn(string $item): bool => $item !== ''));
    }

    /**
     * Casts a raw string to a decoded JSON value.
     *
     * @param string $key The environment variable name.
     * @param string $raw The raw JSON string.
     *
     * @throws ValidationException If the string is not valid JSON.
     *
     * @return mixed
     */
    private function castToJson(string $key, string $raw): mixed
    {
        try {
            return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw ValidationException::typeMismatch($key, 'valid JSON', $raw);
        }
    }
}
