<?php

declare(strict_types=1);

namespace PHPdot\Path\Tests\Unit;

use PHPdot\Config\Configuration;
use PHPdot\Path\PathsConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PathsConfigTest extends TestCase
{
    #[Test]
    public function defaults_use_base_placeholders_with_an_empty_base(): void
    {
        $config = new PathsConfig();

        self::assertSame('', $config->base);
        self::assertSame('{path.base}/public', $config->public);
        self::assertSame('{path.base}/protected', $config->protected);
    }

    #[Test]
    public function it_hydrates_from_the_path_config_section(): void
    {
        $configuration = new Configuration(__DIR__ . '/../Fixtures/config-prefilled');

        $dto = $configuration->dto('path', PathsConfig::class);
        $root = dirname(__DIR__, 2);

        self::assertSame($root, $dto->base);
        self::assertSame($root . '/public', $dto->public);
        self::assertSame($root . '/protected', $dto->protected);
    }
}
