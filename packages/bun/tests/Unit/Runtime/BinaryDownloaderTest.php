<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Unit\Runtime;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPdot\Bun\Config\BunConfig;
use PHPdot\Bun\Exception\BinaryDownloadException;
use PHPdot\Bun\Registry\NpmRegistryClient;
use PHPdot\Bun\Runtime\BinaryDownloader;
use PHPdot\Bun\Tests\Support\FakeHttpClient;
use PHPdot\Bun\Tests\Support\TarGz;
use PHPUnit\Framework\TestCase;

final class BinaryDownloaderTest extends TestCase
{
    private const string PACKAGE = 'test-pkg';
    private const string VERSION = '1.3.14';
    private const string TARBALL_URL = 'https://example.test/test-pkg.tgz';

    private string $dest;

    protected function setUp(): void
    {
        $this->dest = sys_get_temp_dir() . '/phpdot-bun-test-' . uniqid() . '/bun';
    }

    protected function tearDown(): void
    {
        if (is_file($this->dest)) {
            unlink($this->dest);
            @rmdir(dirname($this->dest));
        }
    }

    public function testDownloadsVerifiesAndExtractsBinary(): void
    {
        $tgz = TarGz::build(['package/bin/bun' => 'REAL-BUN-BYTES']);
        $downloader = $this->downloaderFor($tgz, TarGz::integrity($tgz));

        $downloader->download(self::PACKAGE, self::VERSION, $this->dest, 'bun');

        self::assertFileExists($this->dest);
        self::assertSame('REAL-BUN-BYTES', file_get_contents($this->dest));

        if (PHP_OS_FAMILY !== 'Windows') {
            self::assertSame('0755', substr(sprintf('%o', fileperms($this->dest)), -4));
        }
    }

    public function testIntegrityMismatchThrows(): void
    {
        $tgz = TarGz::build(['package/bin/bun' => 'REAL-BUN-BYTES']);
        $downloader = $this->downloaderFor($tgz, 'sha512-' . base64_encode(str_repeat('x', 64)));

        $this->expectException(BinaryDownloadException::class);
        $this->expectExceptionMessage('Integrity check failed');
        $downloader->download(self::PACKAGE, self::VERSION, $this->dest, 'bun');
    }

    public function testMissingVersionThrows(): void
    {
        $tgz = TarGz::build(['package/bin/bun' => 'x']);
        $http = new FakeHttpClient();
        $http->map(
            'https://registry.npmjs.org/' . self::PACKAGE,
            (string) json_encode(['versions' => ['9.9.9' => ['dist' => ['tarball' => self::TARBALL_URL, 'integrity' => TarGz::integrity($tgz)]]]]),
        );
        $http->map(self::TARBALL_URL, $tgz);

        $this->expectException(BinaryDownloadException::class);
        $this->expectExceptionMessage('Version 1.3.14 not found');
        $this->makeDownloader($http)->download(self::PACKAGE, self::VERSION, $this->dest, 'bun');
    }

    public function testRejectsInsecureTarballFromHttpsRegistry(): void
    {
        $http = new FakeHttpClient();
        $http->map(
            'https://registry.npmjs.org/' . self::PACKAGE,
            (string) json_encode(['versions' => [self::VERSION => ['dist' => [
                'tarball' => 'http://insecure.test/test-pkg.tgz',
                'integrity' => 'sha512-' . base64_encode(str_repeat('x', 64)),
            ]]]]),
        );

        $this->expectException(BinaryDownloadException::class);
        $this->expectExceptionMessage('insecure');
        $this->makeDownloader($http)->download(self::PACKAGE, self::VERSION, $this->dest, 'bun');
    }

    public function testBinaryNotInArchiveThrows(): void
    {
        $tgz = TarGz::build(['package/bin/something-else' => 'x']);
        $downloader = $this->downloaderFor($tgz, TarGz::integrity($tgz));

        $this->expectException(BinaryDownloadException::class);
        $this->expectExceptionMessage('not found in archive');
        $downloader->download(self::PACKAGE, self::VERSION, $this->dest, 'bun');
    }

    private function downloaderFor(string $tgz, string $integrity): BinaryDownloader
    {
        $http = new FakeHttpClient();
        $http->map(
            'https://registry.npmjs.org/' . self::PACKAGE,
            (string) json_encode(['versions' => [self::VERSION => ['dist' => ['tarball' => self::TARBALL_URL, 'integrity' => $integrity]]]]),
        );
        $http->map(self::TARBALL_URL, $tgz);

        return $this->makeDownloader($http);
    }

    private function makeDownloader(FakeHttpClient $http): BinaryDownloader
    {
        $factory = new Psr17Factory();

        return new BinaryDownloader($http, $factory, new NpmRegistryClient($http, $factory, new BunConfig()));
    }
}
