<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Unit\Runtime;

use PHPdot\Bun\Exception\UnsupportedPlatformException;
use PHPdot\Bun\Runtime\Platform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlatformTest extends TestCase
{
    /**
     * @return iterable<string, array{Platform, string, ?string}>
     */
    public static function packageProvider(): iterable
    {
        yield 'linux x64 glibc' => [new Platform('linux', 'x64', 'glibc'), '@oven/bun-linux-x64', '@oven/bun-linux-x64-baseline'];
        yield 'linux x64 musl' => [new Platform('linux', 'x64', 'musl'), '@oven/bun-linux-x64-musl', '@oven/bun-linux-x64-musl-baseline'];
        yield 'linux aarch64 glibc' => [new Platform('linux', 'aarch64', 'glibc'), '@oven/bun-linux-aarch64', null];
        yield 'linux aarch64 musl' => [new Platform('linux', 'aarch64', 'musl'), '@oven/bun-linux-aarch64-musl', null];
        yield 'darwin aarch64' => [new Platform('darwin', 'aarch64', null), '@oven/bun-darwin-aarch64', null];
        yield 'darwin x64' => [new Platform('darwin', 'x64', null), '@oven/bun-darwin-x64', '@oven/bun-darwin-x64-baseline'];
        yield 'windows x64' => [new Platform('windows', 'x64', null), '@oven/bun-windows-x64', '@oven/bun-windows-x64-baseline'];
        yield 'windows aarch64' => [new Platform('windows', 'aarch64', null), '@oven/bun-windows-aarch64', null];
    }

    #[DataProvider('packageProvider')]
    public function testNpmPackageMapping(Platform $platform, string $expected, ?string $baseline): void
    {
        self::assertSame($expected, $platform->npmPackage());
        self::assertSame($baseline, $platform->npmPackageBaseline());
    }

    public function testBinaryFilename(): void
    {
        self::assertSame('bun', (new Platform('linux', 'x64', 'glibc'))->binaryFilename());
        self::assertSame('bun', (new Platform('darwin', 'aarch64', null))->binaryFilename());
        self::assertSame('bun.exe', (new Platform('windows', 'x64', null))->binaryFilename());
    }

    public function testUnsupportedOsThrows(): void
    {
        $this->expectException(UnsupportedPlatformException::class);
        (new Platform('bsd', 'x64', null))->npmPackage();
    }

    public function testUnsupportedArchThrows(): void
    {
        $this->expectException(UnsupportedPlatformException::class);
        (new Platform('linux', 'riscv', 'glibc'))->npmPackage();
    }
}
