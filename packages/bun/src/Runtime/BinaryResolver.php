<?php

declare(strict_types=1);

/**
 * Resolves an absolute path to a working Bun binary matching the pinned version, downloading and
 * verifying it on first use.
 *
 * The standard x64 binary can fail to execute on CPUs/VMs without AVX2 (SIGILL / illegal
 * instruction). After a download the binary is probed with `--version`; if it does not run, the
 * resolver falls back to the `-baseline` variant. aarch64 has no baseline — failures there surface.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Runtime;

use PHPdot\Bun\Config\BunConfig;
use PHPdot\Bun\Exception\BinaryDownloadException;
use PHPdot\Bun\Process\ProcessRunnerInterface;
use PHPdot\Container\Attribute\Singleton;

#[Singleton]
final class BinaryResolver
{
    /**
     * Wire the resolver to its config, platform detector, downloader, and process runner.
     *
     * @param BunConfig $config
     * @param PlatformDetector $detector
     * @param BinaryDownloader $downloader
     * @param ProcessRunnerInterface $process
     * @param RuntimeLock $lock
     */
    public function __construct(
        private readonly BunConfig $config,
        private readonly PlatformDetector $detector,
        private readonly BinaryDownloader $downloader,
        private readonly ProcessRunnerInterface $process,
        private readonly RuntimeLock $lock,
    ) {}

    /**
     * Returns the pinned Bun binary path, downloading and verifying it under a lock when missing or stale.
     *
     * @throws BinaryDownloadException
     * @throws \PHPdot\Bun\Exception\UnsupportedPlatformException
     *
     * @return string
     */
    public function resolve(): string
    {
        $platform = $this->detector->detect();
        $dir = $this->runtimeDir();
        $target = $dir . DIRECTORY_SEPARATOR . $platform->binaryFilename();
        $version = $this->config->pinnedVersion;

        if ($this->isValid($target, $version)) {
            return $target;
        }

        return $this->lock->withLock(
            $dir . DIRECTORY_SEPARATOR . '.lock',
            function () use ($platform, $target, $version): string {
                if ($this->isValid($target, $version)) {
                    return $target;
                }

                $this->downloadAndVerify($platform, $target, $version);

                return $target;
            },
        );
    }

    /**
     * Downloads the platform binary and, if it will not execute, retries with the x64 baseline variant.
     *
     * @param Platform $platform
     * @param string $target
     * @param string $version
     *
     * @throws BinaryDownloadException
     *
     * @return void
     */
    private function downloadAndVerify(Platform $platform, string $target, string $version): void
    {
        $this->downloader->download($platform->npmPackage(), $version, $target, $platform->binaryFilename());
        if ($this->isValid($target, $version)) {
            return;
        }

        $baseline = $platform->npmPackageBaseline();
        if ($baseline === null) {
            throw new BinaryDownloadException(sprintf(
                'Bun binary %s did not execute and no baseline variant exists for this platform',
                $platform->npmPackage(),
            ));
        }

        $this->downloader->download($baseline, $version, $target, $platform->binaryFilename());
        if (!$this->isValid($target, $version)) {
            throw new BinaryDownloadException(sprintf('Baseline Bun binary %s failed to execute', $baseline));
        }
    }

    /**
     * Whether the file is an executable Bun binary reporting exactly the pinned version.
     *
     * @phpstan-impure runs a subprocess and reads the filesystem; result changes after a download
     *
     * @param string $path
     * @param string $version
     *
     * @return bool
     */
    private function isValid(string $path, string $version): bool
    {
        if (!is_file($path)) {
            return false;
        }

        try {
            $result = $this->process->run($path, ['--version']);
        } catch (\Throwable) {
            return false;
        }

        return $result->successful() && trim($result->stdout) === $version;
    }

    /**
     * The directory the resolved Bun binary is cached in.
     *
     * @return string
     */
    private function runtimeDir(): string
    {
        $dir = $this->config->runtimeDir;
        if ($this->isAbsolute($dir)) {
            return $dir;
        }

        $cwd = getcwd();

        return ($cwd === false ? '.' : $cwd) . DIRECTORY_SEPARATOR . $dir;
    }

    /**
     * Whether the given filesystem path is absolute.
     *
     * @param string $path
     *
     * @return bool
     */
    private function isAbsolute(string $path): bool
    {
        if (str_starts_with($path, '/')) {
            return true;
        }

        return preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;
    }
}
