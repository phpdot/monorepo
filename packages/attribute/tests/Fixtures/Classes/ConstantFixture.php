<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Fixtures\Classes;

use PHPdot\Attribute\Tests\Fixtures\Attributes\CacheKey;

final class ConstantFixture
{
    #[CacheKey(prefix: 'v1')]
    public const VERSION = '1.0.0';

    #[CacheKey(prefix: 'cfg')]
    public const CONFIG_KEY = 'app.config';

    public const NO_ATTR = 'plain';
}
