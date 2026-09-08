<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Fixtures\Scan\Valid;

use PHPdot\Iam\Authorization\Contract\PolicyInterface;
use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;

final readonly class SecondProbePolicy implements PolicyInterface
{
    public function __invoke(IdentityInterface $identity, ProbeResource $resource): bool
    {
        return $resource->ownerId > 0;
    }
}
