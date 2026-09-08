<?php

declare(strict_types=1);

/**
 * A process/console actor — a command, a queue worker, a migration. There is a
 * single, fixed identity per process; the id is the literal 'cli'.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Identities\Types;

use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;

final readonly class CliIdentity implements IdentityInterface
{
    public function id(): string
    {
        return 'cli';
    }

    public function type(): string
    {
        return 'cli';
    }
}
