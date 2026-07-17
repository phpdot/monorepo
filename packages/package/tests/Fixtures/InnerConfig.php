<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Fixtures;

final readonly class InnerConfig
{
    public function __construct(
        public bool $secure = true,
        public string $sameSite = 'Lax',
        public int $maxAge = 3600,
    ) {}
}
