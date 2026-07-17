<?php

declare(strict_types=1);

/**
 * EnvType
 *
 * Defines the supported value types for environment variables.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Env\Enum;

enum EnvType: string
{
    case STRING = 'string';
    case INT = 'int';
    case FLOAT = 'float';
    case BOOL = 'bool';
    case ENUM = 'enum';
    case LIST = 'list';
    case JSON = 'json';
}
