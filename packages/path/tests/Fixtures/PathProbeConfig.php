<?php

declare(strict_types=1);

namespace PHPdot\Path\Tests\Fixtures;

final class PathProbeConfig
{
    public function __construct(
        public string $templates = '',
    ) {}
}
