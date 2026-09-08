<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Unit\Config;

use PHPdot\Bun\Config\BunConfig;
use PHPdot\Bun\Exception\InvalidBunConfigException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BunConfigTest extends TestCase
{
    #[Test]
    public function absoluteDirectoriesAreAcceptedAndHomeIsDerivedFromResources(): void
    {
        $config = new BunConfig(
            resourcesDir: '/srv/app/resources',
            outputDir: '/srv/app/public/build',
            baseUrl: 'https://cdn.example.com/build',
        );

        self::assertSame('/srv/app/resources/.bun', $config->homeDir, 'home is the .bun yard inside resources — derived, never configured');
        self::assertSame('https://cdn.example.com/build', $config->baseUrl);
    }

    #[Test]
    public function anEmptyDirectoryKeyIsRejectedWithTheRemedy(): void
    {
        $this->expectException(InvalidBunConfigException::class);
        $this->expectExceptionMessage('resourcesDir');

        new BunConfig(resourcesDir: '', outputDir: '/srv/app/public/build');
    }

    #[Test]
    public function aRelativeDirectoryKeyIsRejectedNamingTheDanger(): void
    {
        $this->expectException(InvalidBunConfigException::class);
        $this->expectExceptionMessage('re-download per invocation');

        new BunConfig(resourcesDir: 'relative', outputDir: '/srv/app/public/build');
    }

    #[Test]
    public function outputDirIsGuarded(): void
    {
        $this->expectException(InvalidBunConfigException::class);
        $this->expectExceptionMessage('outputDir');

        new BunConfig(resourcesDir: '/srv/app/resources', outputDir: 'relative');
    }
}
