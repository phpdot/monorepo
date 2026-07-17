<?php

declare(strict_types=1);

/**
 * A single attribute occurrence: instance, arguments, and its exact location.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Attribute\Result;

use PHPdot\Attribute\Enum\TargetType;

final readonly class AttributeResult
{
    /**
     * Create one attribute occurrence with its instance, arguments, and location.
     *
     * @param class-string $attribute
     * @param list<mixed> $arguments
     * @param class-string $class
     * @param object $instance
     * @param TargetType $target
     * @param ?string $method
     * @param ?string $property
     * @param ?string $parameter
     * @param ?string $constant
     */
    public function __construct(
        public string $attribute,
        public object $instance,
        public array $arguments,
        public string $class,
        public TargetType $target,
        public ?string $method = null,
        public ?string $property = null,
        public ?string $parameter = null,
        public ?string $constant = null,
    ) {}
}
