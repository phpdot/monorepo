<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Fixtures\Scan\MalformedPolicy;

use PHPdot\Iam\Authorization\Contract\PolicyInterface;
use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;

final readonly class Broken implements PolicyInterface
{
    public function __invoke(IdentityInterface $identity): bool
    {
        return false;
    }
}
