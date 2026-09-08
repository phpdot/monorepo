<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Fixtures\Scan\DuplicateKey;

use PHPdot\Iam\Authorization\Attribute\Permission;

final class Second
{
    #[Permission(name: 'Two')]
    public const string Key = 'dup.thing.act';
}
