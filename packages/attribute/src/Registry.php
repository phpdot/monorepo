<?php

declare(strict_types=1);

/**
 * Query API over a scanned attribute map.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Attribute;

use PHPdot\Attribute\Enum\StructureType;
use PHPdot\Attribute\Enum\TargetType;
use PHPdot\Attribute\Result\AttributeMap;
use PHPdot\Attribute\Result\AttributeResult;
use PHPdot\Attribute\Result\ClassAttributes;

final class Registry
{
    /**
     * Create a registry over the given attribute map.
     *
     * @param AttributeMap $map
     */
    public function __construct(
        private readonly AttributeMap $map,
    ) {}

    /**
     * Every attribute result in the map, across all classes and targets.
     *
     * @return list<AttributeResult>
     */
    public function all(): array
    {
        $results = [];

        foreach ($this->map->classes as $classAttributes) {
            foreach ($classAttributes->results as $result) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * The number of classes in the underlying map.
     *
     * @return int
     */
    public function count(): int
    {
        return $this->map->count();
    }

    /**
     * The number of occurrences of the given attribute.
     *
     * @param class-string $attributeClass
     *
     * @return int
     */
    public function countByAttribute(string $attributeClass): int
    {
        return count($this->findByAttribute($attributeClass));
    }

    /**
     * All occurrences of the given attribute across the map.
     *
     * @param class-string $attributeClass
     *
     * @return list<AttributeResult>
     */
    public function findByAttribute(string $attributeClass): array
    {
        $results = [];

        foreach ($this->map->classes as $classAttributes) {
            foreach ($classAttributes->results as $result) {
                if ($result->attribute === $attributeClass) {
                    $results[] = $result;
                }
            }
        }

        return $results;
    }

    /**
     * The attribute collection of a class, optionally merged with its parents.
     *
     * @param class-string $className
     * @param bool $includeParents
     *
     * @return ?ClassAttributes
     */
    public function findByClass(string $className, bool $includeParents = false): ?ClassAttributes
    {
        $classAttributes = $this->map->getClass($className);

        if ($classAttributes === null || !$includeParents) {
            return $classAttributes;
        }

        $parentResults = [];
        $parentName = $classAttributes->extends;

        while ($parentName !== null) {
            $parent = $this->map->getClass($parentName);

            if ($parent === null) {
                break;
            }

            foreach ($parent->results as $result) {
                $parentResults[] = $result;
            }

            $parentName = $parent->extends;
        }

        /**
         * @var list<AttributeResult> $merged
         */
        $merged = array_merge($classAttributes->results, $parentResults);

        return new ClassAttributes(
            class: $classAttributes->class,
            structureType: $classAttributes->structureType,
            implements: $classAttributes->implements,
            extends: $classAttributes->extends,
            results: $merged,
        );
    }

    /**
     * The attribute results on one method of one class.
     *
     * @param class-string $className
     * @param string $method
     *
     * @return list<AttributeResult>
     */
    public function findByMethod(string $className, string $method): array
    {
        $classAttributes = $this->map->getClass($className);

        if ($classAttributes === null) {
            return [];
        }

        return $classAttributes->methodAttributes($method);
    }

    /**
     * Class-level results, optionally limited to one attribute.
     *
     * @param class-string|null $attributeClass
     *
     * @return list<AttributeResult>
     */
    public function findClassAttributes(?string $attributeClass = null): array
    {
        return $this->findByTarget($attributeClass, TargetType::CLASS_TYPE);
    }

    /**
     * Names of all scanned structures that are classes.
     *
     * @return list<string>
     */
    public function findClasses(): array
    {
        return $this->findByStructureType(StructureType::CLASS_TYPE);
    }

    /**
     * Constant-level results, optionally limited to one attribute.
     *
     * @param class-string|null $attributeClass
     *
     * @return list<AttributeResult>
     */
    public function findConstantAttributes(?string $attributeClass = null): array
    {
        return $this->findByTarget($attributeClass, TargetType::CONSTANT);
    }

    /**
     * Names of all scanned structures that are enums.
     *
     * @return list<string>
     */
    public function findEnums(): array
    {
        return $this->findByStructureType(StructureType::ENUM_TYPE);
    }

    /**
     * Names of scanned classes extending the given parent.
     *
     * @param string $parentClass
     *
     * @return list<string>
     */
    public function findExtending(string $parentClass): array
    {
        $results = [];

        foreach ($this->map->classes as $classAttributes) {
            if ($classAttributes->extends === $parentClass) {
                $results[] = $classAttributes->class;
            }
        }

        return $results;
    }

    /**
     * Names of scanned classes implementing the given interface.
     *
     * @param string $interface
     *
     * @return list<string>
     */
    public function findImplementing(string $interface): array
    {
        $results = [];

        foreach ($this->map->classes as $classAttributes) {
            if (in_array($interface, $classAttributes->implements, true)) {
                $results[] = $classAttributes->class;
            }
        }

        return $results;
    }

    /**
     * Names of all scanned structures that are interfaces.
     *
     * @return list<string>
     */
    public function findInterfaces(): array
    {
        return $this->findByStructureType(StructureType::INTERFACE_TYPE);
    }

    /**
     * Method-level results, optionally limited to one attribute.
     *
     * @param class-string|null $attributeClass
     *
     * @return list<AttributeResult>
     */
    public function findMethodAttributes(?string $attributeClass = null): array
    {
        return $this->findByTarget($attributeClass, TargetType::METHOD);
    }

    /**
     * Parameter-level results, optionally limited to one attribute.
     *
     * @param class-string|null $attributeClass
     *
     * @return list<AttributeResult>
     */
    public function findParameterAttributes(?string $attributeClass = null): array
    {
        return $this->findByTarget($attributeClass, TargetType::PARAMETER);
    }

    /**
     * Property-level results, optionally limited to one attribute.
     *
     * @param class-string|null $attributeClass
     *
     * @return list<AttributeResult>
     */
    public function findPropertyAttributes(?string $attributeClass = null): array
    {
        return $this->findByTarget($attributeClass, TargetType::PROPERTY);
    }

    /**
     * Names of all scanned structures that are traits.
     *
     * @return list<string>
     */
    public function findTraits(): array
    {
        return $this->findByStructureType(StructureType::TRAIT_TYPE);
    }

    /**
     * Names of classes declaring the given attribute.
     *
     * @param class-string $attributeClass
     *
     * @return list<string>
     */
    public function getClassesWithAttribute(string $attributeClass): array
    {
        $classes = [];

        foreach ($this->map->classes as $classAttributes) {
            if ($classAttributes->has($attributeClass)) {
                $classes[] = $classAttributes->class;
            }
        }

        return $classes;
    }

    /**
     * The underlying attribute map.
     *
     * @return AttributeMap
     */
    public function getMap(): AttributeMap
    {
        return $this->map;
    }

    /**
     * Whether any scanned class declares the given attribute.
     *
     * @param class-string $attributeClass
     *
     * @return bool
     */
    public function hasAttribute(string $attributeClass): bool
    {
        foreach ($this->map->classes as $classAttributes) {
            if ($classAttributes->has($attributeClass)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Names of scanned structures of the given kind.
     *
     * @param StructureType $type
     *
     * @return list<string>
     */
    private function findByStructureType(StructureType $type): array
    {
        $results = [];

        foreach ($this->map->classes as $classAttributes) {
            if ($classAttributes->structureType === $type) {
                $results[] = $classAttributes->class;
            }
        }

        return $results;
    }

    /**
     * Results at one target level, optionally limited to one attribute.
     *
     * @param class-string|null $attributeClass
     * @param TargetType $target
     *
     * @return list<AttributeResult>
     */
    private function findByTarget(?string $attributeClass, TargetType $target): array
    {
        $results = [];

        foreach ($this->map->classes as $classAttributes) {
            foreach ($classAttributes->results as $result) {
                if ($result->target === $target
                    && ($attributeClass === null || $result->attribute === $attributeClass)) {
                    $results[] = $result;
                }
            }
        }

        return $results;
    }
}
