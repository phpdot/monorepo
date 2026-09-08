<?php

declare(strict_types=1);

/**
 * One tool, resolved: what it is called, who may call it, and what runs.
 *
 * A CARRIER. The registry builds these from the attributes it discovers; nothing
 * else constructs one.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mcp\Tool;

final readonly class ToolDescriptor
{
    /**
     * @param string $name The tool's name, as the model sees it
     * @param string $permission The key the caller must hold
     * @param string $description What it answers
     * @param array<string, mixed> $parameters JSON Schema for its arguments
     * @param class-string $class The tool class holding the method
     * @param string $method The method to call
     * @param bool $readOnly Whether the tool does not modify its environment
     * @param bool $destructive Whether the tool may perform destructive updates
     * @param bool $idempotent Whether repeated calls with the same arguments add nothing
     */
    public function __construct(
        public string $name,
        public string $permission,
        public string $description,
        public array $parameters,
        public string $class,
        public string $method,
        public bool $readOnly = false,
        public bool $destructive = false,
        public bool $idempotent = false,
    ) {}
}
