<?php

declare(strict_types=1);

/**
 * The outcome kinds of a stage or the whole engine. A closed set.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication\Enums;

enum AuthenticationStatus
{
    case Authenticated;
    case Pending;
    case Failed;
}
