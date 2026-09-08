<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Fixtures\Scan\Valid;

use PHPdot\Iam\Authorization\Attribute\Permission;

final class Permissions
{
    #[Permission(name: 'View probe', description: 'See probe things')]
    public const string ProbeView = 'probe.thing.view';

    #[Permission(name: 'Rule everything', root: true)]
    public const string RootOnly = 'probe.thing.rule';
}
