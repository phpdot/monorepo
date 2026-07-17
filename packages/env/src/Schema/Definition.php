<?php

declare(strict_types=1);

/**
 * Definition
 *
 * Readonly value object representing a single environment variable's schema definition.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Env\Schema;

use PHPdot\Env\Enum\EnvType;

final readonly class Definition
{
    /**
     * Create one schema entry: type, constraints, and default.
     *
     * @param EnvType $type Variable type.
     * @param class-string<\BackedEnum>|null $enum Enum class (required for ENUM type).
     * @param bool $required Must be present in file or have default.
     * @param bool $notEmpty Must not be empty string after trimming.
     * @param mixed $default Default value when key not in file.
     * @param string|null $description Human-readable description.
     * @param non-empty-string $separator List separator (default ',').
     * @param int|float|null $min Minimum value for INT/FLOAT.
     * @param int|float|null $max Maximum value for INT/FLOAT.
     * @param list<string>|null $allowed Allowed string values.
     * @param string|null $pattern Regex pattern for STRING.
     * @param bool $sensitive Mask in dumps/logs.
     */
    public function __construct(
        public EnvType $type = EnvType::STRING,
        public ?string $enum = null,
        public bool $required = false,
        public bool $notEmpty = false,
        public mixed $default = null,
        public ?string $description = null,
        public string $separator = ',',
        public int|float|null $min = null,
        public int|float|null $max = null,
        public ?array $allowed = null,
        public ?string $pattern = null,
        public bool $sensitive = false,
    ) {}
}
