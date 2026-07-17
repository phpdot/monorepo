<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Fixtures;

use PHPdot\Package\Attribute\InstallHook;

#[InstallHook]
final class InstallHookWithoutHandler {}
