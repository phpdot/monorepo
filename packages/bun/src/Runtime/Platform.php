<?php

declare(strict_types=1);

/**
 * Host platform descriptor: operating system, CPU architecture and (on Linux) libc flavour.
 *
 * Maps the host to the matching `@oven/bun-*` npm package name (see the Bun 1.3.14 platform table).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Runtime;

use PHPdot\Bun\Exception\UnsupportedPlatformException;

final readonly class Platform
{
    /**
     * Captures the resolved OS, architecture, and libc used to pick the Bun npm package.
     *
     * @param string $os 'linux' | 'darwin' | 'windows'
     * @param string $arch 'x64' | 'aarch64'
     * @param string|null $libc 'musl' | 'glibc' | null (non-Linux)
     */
    public function __construct(
        public string $os,
        public string $arch,
        public ?string $libc,
    ) {}

    /**
     * The npm package name for the standard (non-baseline) Bun binary on this platform.
     *
     * @throws UnsupportedPlatformException when the os/arch combination is not supported
     *
     * @return string
     */
    public function npmPackage(): string
    {
        $base = $this->basePackage();

        return $this->libc === 'musl' ? $base . '-musl' : $base;
    }

    /**
     * The npm package name for the x64 "baseline" variant (no AVX2 requirement), or null when
     * this platform has no baseline variant (every non-x64 platform).
     *
     * @throws UnsupportedPlatformException when the os/arch combination is not supported
     *
     * @return ?string
     */
    public function npmPackageBaseline(): ?string
    {
        if ($this->arch !== 'x64') {
            return null;
        }

        return $this->npmPackage() . '-baseline';
    }

    /**
     * The on-disk filename of the extracted binary.
     *
     * @return string
     */
    public function binaryFilename(): string
    {
        return $this->os === 'windows' ? 'bun.exe' : 'bun';
    }

    /**
     * Returns the base @oven/bun npm package for this OS and architecture, or throws when unsupported.
     *
     * @throws UnsupportedPlatformException
     *
     * @return string
     */
    private function basePackage(): string
    {
        $supportedOs = $this->os === 'linux' || $this->os === 'darwin' || $this->os === 'windows';
        $supportedArch = $this->arch === 'x64' || $this->arch === 'aarch64';

        if (!$supportedOs || !$supportedArch) {
            throw new UnsupportedPlatformException(sprintf(
                'Unsupported platform: os=%s arch=%s libc=%s',
                $this->os,
                $this->arch,
                $this->libc ?? 'n/a',
            ));
        }

        return sprintf('@oven/bun-%s-%s', $this->os, $this->arch);
    }
}
