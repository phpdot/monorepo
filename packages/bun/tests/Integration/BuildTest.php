<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Integration;

use PHPdot\Bun\Build\BuildOptions;
use PHPdot\Bun\Bun;
use PHPdot\Bun\Http\HttpClient;
use PHPdot\Bun\Tests\Support\IntegrationBun;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Slice 4 acceptance with a real Bun binary: bundles a fixture with --minify/--splitting/hashed
 * names, and proves --watch rebuilds on change and exits cleanly on signal.
 */
#[Group('integration')]
final class BuildTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        if (getenv('BUN_LIVE') !== '1') {
            self::markTestSkipped('Live Bun integration test — set BUN_LIVE=1 to run it (downloads the real Bun binary over the network).');
        }
        if (!class_exists(HttpClient::class)) {
            self::markTestSkipped('symfony/http-client is required for the integration test');
        }
        $this->dir = sys_get_temp_dir() . '/phpdot-bun-build-' . uniqid();
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (isset($this->dir)) {
            $this->deleteTree($this->dir);
        }
    }

    public function testBundlesWithMinifySplittingAndHashedNames(): void
    {
        // A dynamic import forces code-splitting to emit a separate chunk.
        file_put_contents($this->dir . '/entry.ts', "const m = await import('./shared.ts');\nconsole.log(m.value);\n");
        file_put_contents($this->dir . '/shared.ts', "export const value = 'hello from a shared chunk';\n");

        $exit = $this->bun()->buildWith(
            ['entry.ts'],
            new BuildOptions(outDir: 'dist', target: 'browser', format: 'esm', minify: true, splitting: true, hashedNames: true),
            $this->dir,
        );

        self::assertSame(0, $exit, 'bun build should succeed');

        $jsFiles = (array) glob($this->dir . '/dist/*.js');
        self::assertGreaterThanOrEqual(2, count($jsFiles), '--splitting should emit an entry plus a chunk');

        $hashed = array_filter($jsFiles, static fn($f): bool => is_string($f) && preg_match('/-[A-Za-z0-9]+\.js$/', $f) === 1);
        self::assertNotEmpty($hashed, '--hashed-names should hash the entry filename');
    }

    public function testWatchRebuildsOnChangeAndExitsOnSignal(): void
    {
        if (!extension_loaded('pcntl')) {
            self::markTestSkipped('ext-pcntl is required to forward the termination signal');
        }

        file_put_contents($this->dir . '/entry.ts', "console.log('MARKER_ALPHA');\n");
        $runner = $this->writeWatchRunner();
        $outFile = $this->dir . '/dist/entry.js';

        $process = new Process([PHP_BINARY, $runner], $this->dir);
        $process->setTimeout(null);
        $process->start();

        try {
            self::assertTrue(
                $this->waitUntil(static fn(): bool => is_file($outFile) && str_contains((string) @file_get_contents($outFile), 'MARKER_ALPHA'), 30.0),
                'initial build should produce the bundle: ' . $process->getErrorOutput(),
            );

            // Change the source; --watch should rebuild.
            file_put_contents($this->dir . '/entry.ts', "console.log('MARKER_BETA');\n");
            self::assertTrue(
                $this->waitUntil(static fn(): bool => str_contains((string) @file_get_contents($outFile), 'MARKER_BETA'), 30.0),
                'watch should rebuild after the source changed: ' . $process->getErrorOutput(),
            );

            // A termination signal should bring the long-lived process down cleanly.
            $process->signal(SIGTERM);
            self::assertTrue(
                $this->waitUntil(static fn(): bool => !$process->isRunning(), 15.0),
                'watch process should exit on SIGTERM',
            );
            self::assertFalse($process->isRunning());
        } finally {
            if ($process->isRunning()) {
                $process->stop(1.0, SIGKILL);
            }
        }
    }

    private function bun(): Bun
    {
        return IntegrationBun::create();
    }

    private function writeWatchRunner(): string
    {
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        $runtimeDir = sys_get_temp_dir() . '/phpdot-bun-it-runtime';
        // Drive the real Bun service in a subprocess via the test factory, which constructs the graph
        // directly (no container) — same wiring as the rest of the suite. Nowdoc keeps the template
        // readable; strtr injects the paths.
        $template = <<<'PHP'
            <?php
            require '{{AUTOLOAD}}';

            use PHPdot\Bun\Tests\Support\IntegrationBun;

            exit(IntegrationBun::create('{{RUNTIME}}')->watch('entry.ts', fn ($b) => $b->outDir('dist'), '{{PROJECT}}'));
            PHP;

        $code = strtr($template, [
            '{{AUTOLOAD}}' => $autoload,
            '{{RUNTIME}}' => $runtimeDir,
            '{{PROJECT}}' => $this->dir,
        ]);

        $path = $this->dir . '/watch-runner.php';
        file_put_contents($path, $code);

        return $path;
    }

    private function waitUntil(callable $condition, float $timeoutSeconds): bool
    {
        $deadline = microtime(true) + $timeoutSeconds;
        while (microtime(true) < $deadline) {
            clearstatcache();
            if ($condition() === true) {
                return true;
            }
            usleep(100_000);
        }

        return false;
    }

    private function deleteTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        /** @var list<string> $entries */
        $entries = array_diff((array) scandir($path), ['.', '..']);
        foreach ($entries as $entry) {
            $full = $path . DIRECTORY_SEPARATOR . $entry;
            is_dir($full) && !is_link($full) ? $this->deleteTree($full) : @unlink($full);
        }
        @rmdir($path);
    }
}
