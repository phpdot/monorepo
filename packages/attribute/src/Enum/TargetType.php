<?php

declare(strict_types=1);

/**
 * The declaration site an attribute was found on: class, method, property, parameter, or constant.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Attribute\Enum;

enum TargetType: string
{
    case CLASS_TYPE = 'class';
    case CONSTANT = 'constant';
    case METHOD = 'method';
    case PARAMETER = 'parameter';
    case PROPERTY = 'property';
}
