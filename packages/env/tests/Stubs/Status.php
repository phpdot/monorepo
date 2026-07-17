<?php

declare(strict_types=1);

namespace PHPdot\Env\Tests\Stubs;

enum Status: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
