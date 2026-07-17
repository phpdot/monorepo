<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Integration;

use PHPdot\Bun\Bun;
use PHPdot\Bun\Http\HttpClient;
use PHPdot\Bun\Manifest\Manifest;
use PHPdot\Bun\Tests\Support\IntegrationBun;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Slice 4 acceptance with a real Bun binary: two entrypoints sharing a dependency, built with the
 * production defaults, dedupe the shared code into ONE chunk and write a metafile that the Manifest
 * resolves by source entry.
 */
#[Group('integration')]
final class BuildManifestTest extends TestCase
{
    private const string TOKEN = 'SHARED_TOKEN_X1Y2Z3';

    private string $dir;

    protected function setUp(): void
    {
        if (getenv('BUN_LIVE') !== '1') {
            self::markTestSkipped('Live Bun integration test — set BUN_LIVE=1 to run it (downloads the real Bun binary over the network).');
        }
        if (!class_exists(HttpClient::class)) {
            self::markTestSkipped('symfony/http-client is required for the integration test');
        }
        $this->dir = sys_get_temp_dir() . '/phpdot-bun-manifest-' . uniqid();
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (isset($this->dir)) {
            $this->deleteTree($this->dir);
        }
    }

    public function testSharedChunkDedupAndManifestResolution(): void
    {
        file_put_contents($this->dir . '/shared.ts', "export const SHARED_MARKER = '" . self::TOKEN . "';\n");
        file_put_contents($this->dir . '/index.ts', "import { SHARED_MARKER } from './shared.ts';\nconsole.log('index', SHARED_MARKER);\n");
        file_put_contents($this->dir . '/crm.ts', "import { SHARED_MARKER } from './shared.ts';\nconsole.log('crm', SHARED_MARKER);\n");

        // Production defaults: minify, splitting, hashed names, private verbose metafile + a trimmed
        // deployable manifest written to the output dir.
        $exit = $this->bun()->build(['index.ts', 'crm.ts'], null, $this->dir);
        self::assertSame(0, $exit, 'bun build should succeed');

        $manifest = $this->dir . '/public/build/manifest.json';    // the single, deployable file
        self::assertFileExists($manifest, 'the trimmed, deployable manifest');
        self::assertFileDoesNotExist(
            $this->dir . '/.phpdot/build/metafile.json',
            'the verbose metafile is a throwaway, removed after distilling',
        );

        // The deployable manifest must not leak absolute build paths or the verbose inputs section.
        $manifestJson = (string) file_get_contents($manifest);
        self::assertStringNotContainsString($this->dir, $manifestJson, 'manifest must not leak absolute build paths');
        self::assertStringNotContainsString('inputs', $manifestJson, 'manifest drops the verbose inputs section');

        $jsFiles = array_values(array_filter((array) glob($this->dir . '/public/build/*.js'), is_string(...)));
        self::assertGreaterThanOrEqual(3, count($jsFiles), 'two entries plus a shared chunk');

        // The shared code's literal must appear in exactly one output: the shared chunk.
        $withToken = array_filter(
            $jsFiles,
            static fn(string $f): bool => str_contains((string) file_get_contents($f), self::TOKEN),
        );
        self::assertCount(1, $withToken, 'shared dependency must be deduped into exactly one chunk');

        $resolver = new Manifest($manifest, '/build');
        $indexJs = $resolver->js('index.ts');
        $crmJs = $resolver->js('crm.ts');

        self::assertStringStartsWith('/build/index-', $indexJs);
        self::assertStringEndsWith('.js', $indexJs);
        self::assertStringStartsWith('/build/crm-', $crmJs);
        self::assertNotSame($indexJs, $crmJs);
    }

    private function bun(): Bun
    {
        return IntegrationBun::create();
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
