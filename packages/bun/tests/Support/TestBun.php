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
    public readonly string $binaryPath;

    public function __construct(
        public readonly FakeProcessRunner $runner = new FakeProcessRunner(default: new ProcessResult(0, "1.3.14\n", '')),
        public readonly string $runtimeDir = '',
        ?string $workingDir = null,
    ) {
        $dir = $runtimeDir !== '' ? $runtimeDir : sys_get_temp_dir() . '/phpdot-bun-test-' . uniqid();
        mkdir($dir, 0755, true);

        $config = new BunConfig(runtimeDir: $dir, workingDir: $workingDir);
        $filename = (new PlatformDetector($this->runner))->detect()->binaryFilename();
        $this->binaryPath = $dir . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($this->binaryPath, '#!/bin/sh');

        $factory = new Psr17Factory();
        $http = new FakeHttpClient();
        $downloader = new BinaryDownloader($http, $factory, new NpmRegistryClient($http, $factory, $config));
        $resolver = new BinaryResolver($config, new PlatformDetector($this->runner), $downloader, $this->runner, new RuntimeLock());

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
        if (is_file($this->binaryPath)) {
            unlink($this->binaryPath);
        }
        @rmdir(dirname($this->binaryPath));
    }
}
