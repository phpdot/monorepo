<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Integration;

use PHPdot\Bun\Config\BunConfig;
use PHPdot\Bun\Process\BunProcess;
use PHPdot\Bun\Registry\NpmRegistryClient;
use PHPdot\Bun\Runtime\BinaryDownloader;
use PHPdot\Bun\Runtime\BinaryResolver;
use PHPdot\Bun\Runtime\PlatformDetector;
use PHPdot\Bun\Runtime\RuntimeLock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Psr18Client;

/**
 * Hits the real npm registry: detect platform → download → integrity → extract → `bun --version`.
 * This is the slice's headline acceptance criterion.
 */
#[Group('integration')]
final class ResolveRealBinaryTest extends TestCase
{
    public function testResolvesRealBunBinaryMatchingPin(): void
    {
        if (getenv('BUN_LIVE') !== '1') {
            self::markTestSkipped('Live Bun integration test — set BUN_LIVE=1 to run it (downloads the real Bun binary over the network).');
        }
        if (!class_exists(Psr18Client::class)) {
            self::markTestSkipped('symfony/http-client (Psr18Client) is required for the integration test');
        }

        $runtimeDir = sys_get_temp_dir() . '/phpdot-bun-integration-' . uniqid();
        $config = new BunConfig(runtimeDir: $runtimeDir);
        $process = new BunProcess();
        $psr18 = new Psr18Client();

        $resolver = new BinaryResolver(
            $config,
            new PlatformDetector($process),
            new BinaryDownloader($psr18, $psr18, new NpmRegistryClient($psr18, $psr18, $config)),
            $process,
            new RuntimeLock(),
        );

        $path = $resolver->resolve();

        self::assertFileExists($path);
        $result = $process->run($path, ['--version']);
        self::assertTrue($result->successful(), $result->output());
        self::assertSame($config->pinnedVersion, trim($result->stdout));

        // Cleanup.
        if (is_file($path)) {
            unlink($path);
        }
        @unlink($runtimeDir . '/.lock');
        @rmdir($runtimeDir);
    }
}
