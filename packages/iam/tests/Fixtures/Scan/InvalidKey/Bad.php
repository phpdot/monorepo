<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Fixtures\Scan\InvalidKey;

use PHPdot\Iam\Authorization\Attribute\Permission;

final class Bad
{
    #[Permission(name: 'Bad')]
    public const string Broken = '9not a valid key!';
}
