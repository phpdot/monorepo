<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Fixtures\Scan\Valid;

use PHPdot\Iam\Authorization\IamResource;

final readonly class ProbeResource extends IamResource
{
    public function __construct(
        public int $ownerId,
    ) {}
}
