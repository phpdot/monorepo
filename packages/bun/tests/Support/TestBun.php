<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPdot\Bun\Bun;
use PHPdot\Bun\Config\BunConfig;
use PHPdot\Bun\Process\ProcessResult;
use PHPdot\Bun\Registry\NpmRegistryClient;
use PHPdot\Bun\Runtime\BinaryDownloader;
use PHPdot\Bun\Runtime\BinaryResolver;
use PHPdot\Bun\Runtime\PlatformDetector;
use PHPdot\Bun\Runtime\RuntimeLock;

/**
 * Builds a {@see Bun} whose binary is faked: a valid file sits in a throwaway runtime dir and the
 * runner reports the pinned version, so resolve() returns without any download. Exposes the runner
 * so tests can assert the bun argv each call produced.
 */
final class TestBun
{
    public readonly Bun $bun;

    public readonly BunConfig $config;

    public readonly string $binaryPath;

    public readonly string $home;

    private null|string $ownedRoot = null;

    public function __construct(
        public readonly FakeProcessRunner $runner = new FakeProcessRunner(default: new ProcessResult(0, "1.4.0\n", '')),
        public readonly string $root = '',
    ) {
        $dir = $root !== '' ? $root : sys_get_temp_dir() . '/phpdot-bun-test-' . uniqid();
        $this->ownedRoot = $root === '' ? $dir : null;
        $this->home = $dir . '/resources/.bun';
        mkdir($this->home . '/runtime', 0o755, true);
        mkdir($dir . '/public/build', 0o755, true);

        $config = new BunConfig(resourcesDir: $dir . '/resources', outputDir: $dir . '/public/build');
        $filename = (new PlatformDetector($this->runner))->detect()->binaryFilename();
        $this->binaryPath = $this->home . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($this->binaryPath, "#!/bin/sh\nexit 0\n");
        chmod($this->binaryPath, 0o755);

        $factory = new Psr17Factory();
        $http = new FakeHttpClient();
        $downloader = new BinaryDownloader($http, $factory, new NpmRegistryClient($http, $factory, $config));
        $resolver = new BinaryResolver($config, new PlatformDetector($this->runner), $downloader, $this->runner, new RuntimeLock());

        $this->config = $config;
        $this->bun = new Bun($resolver, $this->runner, $config);
    }

    /**
     * The bun argv produced by the most recent passthrough call.
     *
     * @return list<string>
     */
    public function lastArgs(): array
    {
        return $this->runner->passthroughCalls[count($this->runner->passthroughCalls) - 1]['args'];
    }

    public function lastExecutable(): string
    {
        return $this->runner->passthroughCalls[count($this->runner->passthroughCalls) - 1]['executable'];
    }

    public function cleanup(): void
    {
        if ($this->ownedRoot !== null) {
            $this->removeTree($this->ownedRoot);

            return;
        }

        if (is_file($this->binaryPath)) {
            unlink($this->binaryPath);
        }
        @rmdir(dirname($this->binaryPath));
        @rmdir($this->config->homeDir . '/resources');
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeTree($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
