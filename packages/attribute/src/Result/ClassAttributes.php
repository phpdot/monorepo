<?php

declare(strict_types=1);

/**
 * Every attribute found on a single class, queryable by target.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Attribute\Result;

use PHPdot\Attribute\Enum\StructureType;
use PHPdot\Attribute\Enum\TargetType;

final readonly class ClassAttributes
{
    /**
     * Create the attribute collection of a single class.
     *
     * @param class-string $class
     * @param list<string> $implements
     * @param list<AttributeResult> $results
     * @param StructureType $structureType
     * @param ?string $extends
     */
    public function __construct(
        public string $class,
        public StructureType $structureType,
        public array $implements,
        public ?string $extends,
        public array $results,
    ) {}

    /**
     * Every attribute result found on the class, regardless of target.
     *
     * @return list<AttributeResult>
     */
    public function all(): array
    {
        return $this->results;
    }

    /**
     * Results for attributes declared on the class itself.
     *
     * @return list<AttributeResult>
     */
    public function classAttributes(): array
    {
        return array_values(
            array_filter(
                $this->results,
                static fn(AttributeResult $result): bool => $result->target === TargetType::CLASS_TYPE,
            ),
        );
    }

    /**
     * Results for attributes on constants, optionally filtered by constant name.
     *
     * @param ?string $constant
     *
     * @return list<AttributeResult>
     */
    public function constantAttributes(?string $constant = null): array
    {
        return array_values(
            array_filter(
                $this->results,
                static fn(AttributeResult $result): bool => $result->target === TargetType::CONSTANT
                    && ($constant === null || $result->constant === $constant),
            ),
        );
    }

    /**
     * The first result of the given attribute, or null when absent.
     *
     * @param class-string $attributeClass
     *
     * @return ?AttributeResult
     */
    public function get(string $attributeClass): ?AttributeResult
    {
        foreach ($this->results as $result) {
            if ($result->attribute === $attributeClass) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Whether the class declares the given attribute anywhere.
     *
     * @param class-string $attributeClass
     *
     * @return bool
     */
    public function has(string $attributeClass): bool
    {
        foreach ($this->results as $result) {
            if ($result->attribute === $attributeClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Results for attributes on methods, optionally filtered by method name.
     *
     * @param ?string $method
     *
     * @return list<AttributeResult>
     */
    public function methodAttributes(?string $method = null): array
    {
        return array_values(
            array_filter(
                $this->results,
                static fn(AttributeResult $result): bool => $result->target === TargetType::METHOD
                    && ($method === null || $result->method === $method),
            ),
        );
    }

    /**
     * Results for attributes on parameters, optionally filtered by method and parameter.
     *
     * @param ?string $parameter
     * @param ?string $method
     *
     * @return list<AttributeResult>
     */
    public function parameterAttributes(?string $method = null, ?string $parameter = null): array
    {
        return array_values(
            array_filter(
                $this->results,
                static fn(AttributeResult $result): bool => $result->target === TargetType::PARAMETER
                    && ($method === null || $result->method === $method)
                    && ($parameter === null || $result->parameter === $parameter),
            ),
        );
    }

    /**
     * Results for attributes on properties, optionally filtered by property name.
     *
     * @param ?string $property
     *
     * @return list<AttributeResult>
     */
    public function propertyAttributes(?string $property = null): array
    {
        return array_values(
            array_filter(
                $this->results,
                static fn(AttributeResult $result): bool => $result->target === TargetType::PROPERTY
                    && ($property === null || $result->property === $property),
            ),
        );
    }
}
