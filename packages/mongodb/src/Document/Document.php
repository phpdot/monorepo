<?php

declare(strict_types=1);

/**
 * Typed document wrapper with automatic BSON→PHP conversion on access.
 *
 * Converts BSON types to PHP natives: UTCDateTime→DateTimeImmutable,
 * BSONDocument→Document (recursive), BSONArray→array, Int64→int, Binary→string.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\MongoDB\Document;

use ArrayAccess;
use JsonSerializable;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\Document as BSONDocument;
use MongoDB\BSON\Int64;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\PackedArray;
use MongoDB\BSON\UTCDateTime;

/**
 * @implements ArrayAccess<string, mixed>
 */
final class Document implements ArrayAccess, JsonSerializable
{
    /**
     * @var array<string, mixed>
     */
    private readonly array $data;

    /**
     * Hold a document's raw data for lazy BSON-to-PHP conversion on access.
     *
     * @param array<string, mixed> $data Raw document data from MongoDB
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Create a Document from a MongoDB BSON document, array, or stdClass-like object.
     *
     * Accepts MongoDB\BSON\Document, MongoDB\Model\BSONDocument, arrays, and stdClass.
     *
     * @param object|array<int|string, mixed> $source Source data
     *
     * @return self
     */
    public static function fromBSON(object|array $source): self
    {
        if ($source instanceof BSONDocument) {
            /**
             * @var array<string, mixed> $array
             */
            $array = $source->toPHP([
                'root' => 'array',
                'document' => 'array',
                'array' => 'array',
            ]);

            return new self($array);
        }

        if (is_object($source)) {
            /**
             * @var array<string, mixed> $array
             */
            $array = self::deepConvertToArray($source);

            return new self($array);
        }

        /**
         * @var array<string, mixed> $array
         */
        $array = $source;

        return new self($array);
    }

    /**
     * Get the document's _id field as an ObjectId.
     *
     * @return ?ObjectId
     */
    public function id(): ?ObjectId
    {
        $id = $this->data['_id'] ?? null;

        return $id instanceof ObjectId ? $id : null;
    }

    /**
     * Get a field value with automatic BSON→PHP type conversion.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        if (!array_key_exists($name, $this->data)) {
            return null;
        }

        return self::convertValue($this->data[$name]);
    }

    /**
     * Check if a field exists in the document.
     *
     * @param string $name
     *
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    /**
     * Check if the document has a specific field.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Get a field value with an optional default.
     *
     * @param mixed $default
     * @param string $key
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!array_key_exists($key, $this->data)) {
            return $default;
        }

        return self::convertValue($this->data[$key]);
    }

    /**
     * Convert the entire document to a plain PHP array (recursive).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return self::convertArray($this->data);
    }

    /**
     * Convert the document to a JSON string.
     *
     * @param int $flags
     *
     * @return string
     */
    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), $flags | JSON_THROW_ON_ERROR);
    }

    /**
     * Get the raw data without type conversion.
     *
     * @return array<string, mixed>
     */
    public function getRaw(): array
    {
        return $this->data;
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->data);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->__get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Document is immutable');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Document is immutable');
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convert a single BSON value to its PHP equivalent.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    private static function convertValue(mixed $value): mixed
    {
        if ($value instanceof UTCDateTime) {
            return \DateTimeImmutable::createFromMutable($value->toDateTime())->setTimezone(new \DateTimeZone('UTC'));
        }

        if ($value instanceof BSONDocument) {
            return self::fromBSON($value);
        }

        if ($value instanceof PackedArray) {
            /**
             * @var list<mixed> $array
             */
            $array = $value->toPHP([
                'root' => 'array',
                'document' => 'array',
                'array' => 'array',
            ]);

            return self::convertArrayValues($array);
        }

        if ($value instanceof Int64) {
            return (int) (string) $value;
        }

        if ($value instanceof Binary) {
            return $value->getData();
        }

        if (is_array($value)) {
            if ($value !== [] && !array_is_list($value)) {
                /**
                 * @var array<string, mixed> $value
                 */
                return new self($value);
            }

            return self::convertArrayValues($value);
        }

        return $value;
    }

    /**
     * Recursively convert an associative array's values for toArray().
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private static function convertArray(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $result[$key] = self::convertForArray($value);
        }

        return $result;
    }

    /**
     * Recursively convert array values.
     *
     * @param list<mixed> $values
     *
     * @return list<mixed>
     */
    private static function convertArrayValues(array $values): array
    {
        return array_map(self::convertValue(...), $values);
    }

    /**
     * Convert a value for inclusion in toArray() output (plain PHP types only).
     *
     * @param mixed $value
     *
     * @return mixed
     */
    private static function convertForArray(mixed $value): mixed
    {
        if ($value instanceof UTCDateTime) {
            return \DateTimeImmutable::createFromMutable($value->toDateTime())->setTimezone(new \DateTimeZone('UTC'));
        }

        if ($value instanceof BSONDocument) {
            /**
             * @var array<string, mixed> $array
             */
            $array = $value->toPHP([
                'root' => 'array',
                'document' => 'array',
                'array' => 'array',
            ]);

            return self::convertArray($array);
        }

        if ($value instanceof PackedArray) {
            /**
             * @var list<mixed> $array
             */
            $array = $value->toPHP([
                'root' => 'array',
                'document' => 'array',
                'array' => 'array',
            ]);

            return array_map(self::convertForArray(...), $array);
        }

        if ($value instanceof Int64) {
            return (int) (string) $value;
        }

        if ($value instanceof Binary) {
            return $value->getData();
        }

        if ($value instanceof ObjectId) {
            return (string) $value;
        }

        if ($value instanceof Decimal128) {
            return (string) $value;
        }

        if (is_array($value)) {
            if ($value !== [] && !array_is_list($value)) {
                /**
                 * @var array<string, mixed> $value
                 */
                return self::convertArray($value);
            }

            return array_map(self::convertForArray(...), $value);
        }

        return $value;
    }

    /**
     * Recursively convert a BSON object (BSONDocument, BSONArray, stdClass) into a plain PHP array.
     * Preserves BSON value types (ObjectId, UTCDateTime, etc.) but converts container types.
     *
     * @param object $source
     *
     * @return array<string, mixed>
     */
    private static function deepConvertToArray(object $source): array
    {
        /**
         * @var array<int|string, mixed> $raw
         */
        $raw = method_exists($source, 'getArrayCopy') ? $source->getArrayCopy() : (array) $source;

        $result = [];
        foreach ($raw as $key => $value) {
            $result[(string) $key] = self::deepConvertValue($value);
        }

        return $result;
    }

    /**
     * Recursively convert a single BSON container value to plain PHP.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    private static function deepConvertValue(mixed $value): mixed
    {
        if (!is_object($value)) {
            return $value;
        }

        if ($value instanceof ObjectId
            || $value instanceof UTCDateTime
            || $value instanceof Decimal128
            || $value instanceof Binary
            || $value instanceof Int64
            || $value instanceof BSONDocument
            || $value instanceof PackedArray) {
            return $value;
        }

        if (method_exists($value, 'getArrayCopy')) {
            /**
             * @var array<int|string, mixed> $items
             */
            $items = $value->getArrayCopy();

            $result = [];
            foreach ($items as $k => $v) {
                $result[$k] = self::deepConvertValue($v);
            }

            return $result;
        }

        return (array) $value;
    }
}
