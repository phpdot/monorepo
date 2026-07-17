<?php

declare(strict_types=1);

/**
 * The kind of PHP structure an attribute was found on: class, interface, enum, or trait.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Attribute\Enum;

enum StructureType: string
{
    case CLASS_TYPE = 'class';
    case ENUM_TYPE = 'enum';
    case INTERFACE_TYPE = 'interface';
    case TRAIT_TYPE = 'trait';
}
