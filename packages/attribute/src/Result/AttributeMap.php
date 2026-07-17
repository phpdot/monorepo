<?php

declare(strict_types=1);

/**
 * Immutable result of an attribute scan, keyed by class name.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Attribute\Result;

use PHPdot\Attribute\Enum\StructureType;
use PHPdot\Attribute\Enum\TargetType;

final readonly class AttributeMap
{
    /**
     * Create a map of scan results with its generation metadata.
     *
     * @param array<string, ClassAttributes> $classes
     * @param list<string> $directories
     * @param list<string> $filter
     * @param int $generatedAt
     */
    public function __construct(
        public array $classes,
        public int $generatedAt,
        public array $directories,
        public array $filter,
    ) {}

    /**
     * Rebuild a map, attribute instances included, from its cached array form.
     *
     * @param array{
     *     classes: array<string, array{
     *         class: string,
     *         structureType: string,
     *         implements: list<string>,
     *         extends: ?string,
     *         results: list<array{
     *             attribute: string,
     *             arguments: list<mixed>,
     *             class: string,
     *             target: string,
     *             method: ?string,
     *             property: ?string,
     *             parameter: ?string,
     *             constant: ?string
     *         }>
     *     }>,
     *     generatedAt: int,
     *     directories: list<string>,
     *     filter: list<string>
     * } $data
     *
     * @return self
     */
    public static function fromCache(array $data): self
    {
        $classes = [];

        foreach ($data['classes'] as $className => $classData) {
            $results = [];

            foreach ($classData['results'] as $resultData) {
                /**
                 * @var class-string $attr
                 */
                $attr = $resultData['attribute'];
                /**
                 * @var list<mixed> $args
                 */
                $args = $resultData['arguments'];

                /**
                 * @var class-string $resultClass
                 */
                $resultClass = $resultData['class'];

                $results[] = new AttributeResult(
                    attribute: $attr,
                    instance: new $attr(...$args),
                    arguments: $args,
                    class: $resultClass,
                    target: TargetType::from($resultData['target']),
                    method: $resultData['method'],
                    property: $resultData['property'],
                    parameter: $resultData['parameter'],
                    constant: $resultData['constant'],
                );
            }

            /**
             * @var class-string $classDataClass
             */
            $classDataClass = $classData['class'];

            $classes[$className] = new ClassAttributes(
                class: $classDataClass,
                structureType: StructureType::from($classData['structureType']),
                implements: $classData['implements'],
                extends: $classData['extends'],
                results: $results,
            );
        }

        return new self(
            classes: $classes,
            generatedAt: $data['generatedAt'],
            directories: $data['directories'],
            filter: $data['filter'],
        );
    }

    /**
     * The number of classes in the map.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->classes);
    }

    /**
     * The attributes of the given class, or null when it is not in the map.
     *
     * @param string $className
     *
     * @return ?ClassAttributes
     */
    public function getClass(string $className): ?ClassAttributes
    {
        return $this->classes[$className] ?? null;
    }

    /**
     * All scanned classes and their attributes, keyed by class name.
     *
     * @return array<string, ClassAttributes>
     */
    public function getClasses(): array
    {
        return $this->classes;
    }

    /**
     * Whether the map contains the given class.
     *
     * @param string $className
     *
     * @return bool
     */
    public function hasClass(string $className): bool
    {
        return isset($this->classes[$className]);
    }

    /**
     * Serialize the map to a plain array suitable for the file cache.
     *
     * @return array{
     *     classes: array<string, array{
     *         class: string,
     *         structureType: string,
     *         implements: list<string>,
     *         extends: ?string,
     *         results: list<array{
     *             attribute: string,
     *             arguments: list<mixed>,
     *             class: string,
     *             target: string,
     *             method: ?string,
     *             property: ?string,
     *             parameter: ?string,
     *             constant: ?string
     *         }>
     *     }>,
     *     generatedAt: int,
     *     directories: list<string>,
     *     filter: list<string>
     * }
     */
    public function toCache(): array
    {
        $classes = [];

        foreach ($this->classes as $className => $classAttributes) {
            $results = [];

            foreach ($classAttributes->results as $result) {
                $results[] = [
                    'attribute' => $result->attribute,
                    'arguments' => $result->arguments,
                    'class' => $result->class,
                    'target' => $result->target->value,
                    'method' => $result->method,
                    'property' => $result->property,
                    'parameter' => $result->parameter,
                    'constant' => $result->constant,
                ];
            }

            $classes[$className] = [
                'class' => $classAttributes->class,
                'structureType' => $classAttributes->structureType->value,
                'implements' => $classAttributes->implements,
                'extends' => $classAttributes->extends,
                'results' => $results,
            ];
        }

        return [
            'classes' => $classes,
            'generatedAt' => $this->generatedAt,
            'directories' => $this->directories,
            'filter' => $this->filter,
        ];
    }
}
