<?php

declare(strict_types=1);

/**
 * Detects the host platform at runtime: OS family, CPU architecture and (on Linux) libc flavour.
 *
 * This runs on the developer's / server's machine, never in CI.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Runtime;

use PHPdot\Bun\Exception\UnsupportedPlatformException;
use PHPdot\Bun\Process\ProcessRunnerInterface;
use PHPdot\Container\Attribute\Singleton;

#[Singleton]
final class PlatformDetector
{
    /**
     * Bind the detector to the process runner it queries for platform details.
     *
     * @param ProcessRunnerInterface $process
     */
    public function __construct(
        private readonly ProcessRunnerInterface $process,
    ) {}

    /**
     * Detect the current platform (OS and architecture) for binary selection.
     *
     * @return Platform
     */
    public function detect(): Platform
    {
        $os = $this->detectOs();
        $arch = $this->detectArch();
        $libc = $os === 'linux' ? $this->detectLibc() : null;

        return new Platform($os, $arch, $libc);
    }

    /**
     * Maps PHP_OS_FAMILY to Bun's OS token, throwing when the host OS is unsupported.
     *
     * @throws UnsupportedPlatformException
     *
     * @return string
     */
    private function detectOs(): string
    {
        return match (PHP_OS_FAMILY) {
            'Linux' => 'linux',
            'Darwin' => 'darwin',
            'Windows' => 'windows',
            default => throw new UnsupportedPlatformException(
                sprintf('Unsupported OS family: %s', PHP_OS_FAMILY),
            ),
        };
    }

    /**
     * Maps the host machine architecture to Bun's arch token, throwing when it is unsupported.
     *
     * @throws UnsupportedPlatformException
     *
     * @return string
     */
    private function detectArch(): string
    {
        $machine = strtolower(trim(php_uname('m')));

        return match ($machine) {
            'x86_64', 'amd64', 'x64' => 'x64',
            'arm64', 'aarch64' => 'aarch64',
            default => throw new UnsupportedPlatformException(
                sprintf('Unsupported architecture: %s', $machine),
            ),
        };
    }

    /**
     * Probe order: Alpine marker file, then `ldd --version`. Defaults to glibc when indeterminate;
     * the resolver's baseline/standard `--version` check covers any misdetection.
     *
     * @return string
     */
    private function detectLibc(): string
    {
        if (file_exists('/etc/alpine-release')) {
            return 'musl';
        }

        try {
            $result = $this->process->run('ldd', ['--version']);
        } catch (\Throwable) {
            return 'glibc';
        }

        return str_contains(strtolower($result->output()), 'musl') ? 'musl' : 'glibc';
    }
}
