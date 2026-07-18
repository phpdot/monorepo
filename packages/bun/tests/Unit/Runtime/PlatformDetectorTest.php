<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Unit\Runtime;

use PHPdot\Bun\Process\ProcessResult;
use PHPdot\Bun\Runtime\PlatformDetector;
use PHPdot\Bun\Tests\Support\FakeProcessRunner;
use PHPUnit\Framework\TestCase;

final class PlatformDetectorTest extends TestCase
{
    public function testDetectsHostOsAndArch(): void
    {
        $detector = new PlatformDetector(new FakeProcessRunner(default: new ProcessResult(0, 'glibc', '')));
        $platform = $detector->detect();

        $expectedOs = match (PHP_OS_FAMILY) {
            'Linux' => 'linux',
            'Darwin' => 'darwin',
            'Windows' => 'windows',
            default => null,
        };

        if ($expectedOs === null) {
            self::markTestSkipped('Unsupported host OS family: ' . PHP_OS_FAMILY);
        }

        self::assertSame($expectedOs, $platform->os);
        self::assertContains($platform->arch, ['x64', 'aarch64']);

        if ($expectedOs !== 'linux') {
            self::assertNull($platform->libc, 'libc is only detected on Linux');
        }
    }

    public function testLddMuslOutputYieldsMuslLibc(): void
    {
        $runner = new FakeProcessRunner([new ProcessResult(1, '', 'musl libc (x86_64)')]);
        $detector = new PlatformDetector($runner);

        $libc = (new \ReflectionMethod($detector, 'detectLibc'))->invoke($detector);

        self::assertSame('musl', $libc);
    }
}
