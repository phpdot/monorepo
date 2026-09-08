<?php

declare(strict_types=1);

/**
 * One tool as a provider needs to be told about it.
 *
 * The drivers' input, and deliberately NOT a permission-carrying tool descriptor of
 * the kind a tool registry keeps: a descriptor carries a permission key and the class
 * and method that run it, none of which any vendor should ever be sent. This is the
 * three facts that cross the wire.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Contract;

final readonly class ToolDefinition
{
    /**
     * @param string $name What the model calls it
     * @param string $description What it does
     * @param array<string, mixed> $parameters JSON Schema for its arguments
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $parameters,
    ) {}
}
