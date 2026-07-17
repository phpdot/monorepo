<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Unit\Runtime;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPdot\Bun\Config\BunConfig;
use PHPdot\Bun\Exception\BinaryDownloadException;
use PHPdot\Bun\Process\ProcessResult;
use PHPdot\Bun\Registry\NpmRegistryClient;
use PHPdot\Bun\Runtime\BinaryDownloader;
use PHPdot\Bun\Runtime\BinaryResolver;
use PHPdot\Bun\Runtime\PlatformDetector;
use PHPdot\Bun\Runtime\RuntimeLock;
use PHPdot\Bun\Tests\Support\FakeHttpClient;
use PHPdot\Bun\Tests\Support\FakeProcessRunner;
use PHPdot\Bun\Tests\Support\TarGz;
use PHPUnit\Framework\TestCase;

final class BinaryResolverTest extends TestCase
{
    private const string VERSION = '1.3.14';

    private string $runtimeDir;

    protected function setUp(): void
    {
        $this->runtimeDir = sys_get_temp_dir() . '/phpdot-bun-resolve-' . uniqid();
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->runtimeDir)) {
            return;
        }
        // scandir lists hidden entries (the .lock file) and is portable; GLOB_BRACE is glibc-only.
        foreach ((array) scandir($this->runtimeDir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $this->runtimeDir . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path)) {
                @unlink($path);
            }
        }
        @rmdir($this->runtimeDir);
    }

    public function testResolvesAndCachesWhenStandardBinaryWorks(): void
    {
        $detector = $this->detector();
        $platform = $detector->detect();
        $stdTgz = TarGz::build(['package/bin/' . $platform->binaryFilename() => 'STD-BINARY']);

        $http = new FakeHttpClient();
        $stdUrl = 'https://example.test/std.tgz';
        $this->mapPackage($http, $platform->npmPackage(), $stdUrl, $stdTgz);

        $runner = new FakeProcessRunner([$this->valid(), $this->valid()]);
        $resolver = $this->resolver($http, $detector, $runner);

        $path = $resolver->resolve();

        self::assertSame($this->runtimeDir . DIRECTORY_SEPARATOR . $platform->binaryFilename(), $path);
        self::assertSame('STD-BINARY', file_get_contents($path));

        // Second resolve finds a valid cached binary and must not re-download.
        $resolver->resolve();
        self::assertSame(1, $http->hits[$stdUrl] ?? 0);
    }

    public function testBaselineFallbackOrThrowWhenStandardBinaryFails(): void
    {
        $detector = $this->detector();
        $platform = $detector->detect();
        $http = new FakeHttpClient();

        $stdUrl = 'https://example.test/std.tgz';
        $this->mapPackage(
            $http,
            $platform->npmPackage(),
            $stdUrl,
            TarGz::build(['package/bin/' . $platform->binaryFilename() => 'STD-BINARY']),
        );

        $baseline = $platform->npmPackageBaseline();

        if ($baseline === null) {
            // aarch64: no baseline variant — a failing standard binary must surface as an error.
            $resolver = $this->resolver($http, $detector, new FakeProcessRunner([$this->invalid()]));
            $this->expectException(BinaryDownloadException::class);
            $this->expectExceptionMessage('no baseline variant exists');
            $resolver->resolve();

            return;
        }

        $baseUrl = 'https://example.test/base.tgz';
        $this->mapPackage(
            $http,
            $baseline,
            $baseUrl,
            TarGz::build(['package/bin/' . $platform->binaryFilename() => 'BASE-BINARY']),
        );

        $resolver = $this->resolver($http, $detector, new FakeProcessRunner([$this->invalid(), $this->valid()]));
        $path = $resolver->resolve();

        self::assertSame('BASE-BINARY', file_get_contents($path));
        self::assertSame(1, $http->hits[$baseUrl] ?? 0);
    }

    private function mapPackage(FakeHttpClient $http, string $package, string $tarballUrl, string $tgz): void
    {
        $http->map(
            'https://registry.npmjs.org/' . $package,
            (string) json_encode(['versions' => [self::VERSION => ['dist' => ['tarball' => $tarballUrl, 'integrity' => TarGz::integrity($tgz)]]]]),
        );
        $http->map($tarballUrl, $tgz);
    }

    private function resolver(FakeHttpClient $http, PlatformDetector $detector, FakeProcessRunner $runner): BinaryResolver
    {
        $factory = new Psr17Factory();
        $config = new BunConfig(runtimeDir: $this->runtimeDir);
        $downloader = new BinaryDownloader($http, $factory, new NpmRegistryClient($http, $factory, $config));

        return new BinaryResolver($config, $detector, $downloader, $runner, new RuntimeLock());
    }

    private function detector(): PlatformDetector
    {
        return new PlatformDetector(new FakeProcessRunner(default: new ProcessResult(0, 'glibc', '')));
    }

    private function valid(): ProcessResult
    {
        return new ProcessResult(0, self::VERSION . "\n", '');
    }

    private function invalid(): ProcessResult
    {
        return new ProcessResult(132, '', 'illegal instruction (core dumped)');
    }
}
