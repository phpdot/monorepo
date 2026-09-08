<?php

declare(strict_types=1);

/**
 * The life of a mirrored permission: declared in code, or declared once and
 * gone. Sync marks a still-stored key `orphaned` instead of deleting it —
 * grants referencing it keep failing closed — and a grant to an orphaned key
 * is refused, because no code path will ever check it.
 *
 * Backed by the stored string: the mirror column is the vocabulary's home,
 * and this enum is its type.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\Enum;

enum PermissionStatus: string
{
    case Active = 'active';

    case Orphaned = 'orphaned';
}
