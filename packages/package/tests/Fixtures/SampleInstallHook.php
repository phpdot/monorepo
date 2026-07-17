<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Fixtures;

use PHPdot\Package\Attribute\InstallHook;
use PHPdot\Package\Contract\InstallHandler;

#[InstallHook]
final class SampleInstallHook implements InstallHandler
{
    public static function install(string $projectRoot, string $configDir): ?string
    {
        return null;
    }
}
