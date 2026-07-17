<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Unit\Manifest;

use PHPdot\Bun\Manifest\Manifest;
use PHPdot\Bun\Manifest\ManifestEntryNotFoundException;
use PHPdot\Bun\Manifest\ManifestNotReadableException;
use PHPUnit\Framework\TestCase;

final class ManifestTest extends TestCase
{
    private string $metafile;

    protected function setUp(): void
    {
        $this->metafile = (string) tempnam(sys_get_temp_dir(), 'phpdot-bun-meta');
    }

    protected function tearDown(): void
    {
        if (is_file($this->metafile)) {
            unlink($this->metafile);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $outputs
     */
    private function write(array $outputs): void
    {
        file_put_contents($this->metafile, (string) json_encode(['outputs' => $outputs], JSON_THROW_ON_ERROR));
    }

    public function testResolvesJsAndCssForTheSameEntryPoint(): void
    {
        $this->write([
            './index-74g4yfem.js' => ['entryPoint' => 'resources/js/index.ts'],
            './index-3261p4x3.css' => ['entryPoint' => 'resources/js/index.ts'],
            './index-0ewgenf0.js' => [], // shared chunk: no entryPoint, must be ignored
        ]);

        $manifest = new Manifest($this->metafile, '/build');

        self::assertSame('/build/index-74g4yfem.js', $manifest->js('resources/js/index.ts'));
        self::assertSame('/build/index-3261p4x3.css', $manifest->css('resources/js/index.ts'));
    }

    public function testPreservesSubdirectoriesForNestedEntries(): void
    {
        // Real bun keys are relative to the outdir: a root entry gets a "././" prefix, a nested one
        // keeps its subdir. The nested entry must resolve to a nested URL, not a flattened basename.
        $this->write([
            '././app-z0dec2hx.js' => ['entryPoint' => 'resources/js/app.ts'],
            './admin/panel-spxqhf1q.js' => ['entryPoint' => 'resources/js/admin/panel.ts'],
        ]);

        $manifest = new Manifest($this->metafile, '/build');

        self::assertSame('/build/app-z0dec2hx.js', $manifest->js('resources/js/app.ts'));
        self::assertSame('/build/admin/panel-spxqhf1q.js', $manifest->js('resources/js/admin/panel.ts'));
    }

    public function testResolvesStandaloneCssEntry(): void
    {
        $this->write(['./app-9a8b.css' => ['entryPoint' => 'resources/css/app.css']]);

        self::assertSame('/build/app-9a8b.css', (new Manifest($this->metafile))->css('resources/css/app.css'));
    }

    public function testCustomPublicPrefixIsTrimmed(): void
    {
        $this->write(['./app-9a8b.js' => ['entryPoint' => 'a.ts']]);

        self::assertSame('/assets/app-9a8b.js', (new Manifest($this->metafile, '/assets/'))->js('a.ts'));
    }

    public function testUnknownEntryThrows(): void
    {
        $this->write(['./a-1.js' => ['entryPoint' => 'a.ts']]);

        $this->expectException(ManifestEntryNotFoundException::class);
        (new Manifest($this->metafile))->js('missing.ts');
    }

    public function testUnknownExtensionThrows(): void
    {
        $this->write(['./a-1.js' => ['entryPoint' => 'a.ts']]);

        $this->expectException(ManifestEntryNotFoundException::class);
        (new Manifest($this->metafile))->css('a.ts');
    }

    public function testUnreadableMetafileThrows(): void
    {
        $missing = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpdot-bun-missing-' . uniqid() . DIRECTORY_SEPARATOR . 'manifest.json';

        $this->expectException(ManifestNotReadableException::class);
        (new Manifest($missing))->js('a.ts');
    }

    public function testMalformedMetafileThrowsUnreadable(): void
    {
        file_put_contents($this->metafile, '{"outputs": {"./app.js": {"entryPoint": "app.ts"');

        $this->expectException(ManifestNotReadableException::class);
        (new Manifest($this->metafile))->js('app.ts');
    }

    public function testCompileKeepsOnlyOutputsAndDropsInputs(): void
    {
        $verbose = (string) tempnam(sys_get_temp_dir(), 'phpdot-bun-verbose');
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpdot-bun-compiled-' . uniqid() . DIRECTORY_SEPARATOR . 'manifest.json';

        // Bun records an absolute, host-specific path under inputs.imports[].path — the part that
        // must not survive into the deployable manifest. Derive a real path for whatever OS runs the
        // test rather than hardcoding one.
        $absoluteSource = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'app-source' . DIRECTORY_SEPARATOR . 'dep.ts';

        file_put_contents($verbose, (string) json_encode([
            'inputs' => [
                'app.ts' => ['imports' => [['path' => $absoluteSource]]],
            ],
            'outputs' => [
                './app-abc.js' => ['entryPoint' => 'app.ts'],
                './chunk-xyz.js' => [], // shared chunk: no entryPoint
            ],
        ], JSON_THROW_ON_ERROR));

        self::assertTrue(Manifest::compile($verbose, $target));

        $json = (string) file_get_contents($target);
        self::assertStringNotContainsString('inputs', $json, 'the inputs section must be dropped');
        self::assertStringNotContainsString($absoluteSource, $json, 'absolute input paths must not survive');
        self::assertSame('/build/app-abc.js', (new Manifest($target, '/build'))->js('app.ts'));

        unlink($verbose);
        unlink($target);
        @rmdir(dirname($target));
    }

    public function testCompileReturnsFalseWhenMetafileIsUnreadable(): void
    {
        $missing = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpdot-bun-no-meta-' . uniqid() . '.json';
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpdot-bun-no-out-' . uniqid() . DIRECTORY_SEPARATOR . 'manifest.json';

        self::assertFalse(Manifest::compile($missing, $target), 'an unreadable metafile yields no manifest');
        self::assertFileDoesNotExist($target);
    }

    public function testCompileReturnsFalseOnMalformedMetafile(): void
    {
        // A corrupt/truncated metafile (bun killed mid-write, disk full) must fail rather than
        // silently distil to an empty {"outputs":[]} that later throws at every js()/css() call.
        $corrupt = (string) tempnam(sys_get_temp_dir(), 'phpdot-bun-badmeta');
        file_put_contents($corrupt, '{"outputs": {"./app.js": {"entryPoint": "app.ts"');
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpdot-bun-badmeta-out-' . uniqid() . DIRECTORY_SEPARATOR . 'manifest.json';

        self::assertFalse(Manifest::compile($corrupt, $target), 'a malformed metafile must not yield a manifest');
        self::assertFileDoesNotExist($target);

        unlink($corrupt);
    }
}
