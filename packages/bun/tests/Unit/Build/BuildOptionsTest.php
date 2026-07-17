<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Unit\Build;

use PHPdot\Bun\Build\BuildOptions;
use PHPUnit\Framework\TestCase;

final class BuildOptionsTest extends TestCase
{
    public function testEmptyOptionsProduceNoFlags(): void
    {
        self::assertSame([], (new BuildOptions())->toArguments());
    }

    public function testHashedNamesAlsoHashesChunks(): void
    {
        self::assertSame(
            ['--entry-naming=[dir]/[name]-[hash].[ext]', '--chunk-naming=[name]-[hash].[ext]'],
            (new BuildOptions(hashedNames: true))->toArguments(),
        );
    }

    public function testExplicitChunkNamingOverridesHashedDefault(): void
    {
        self::assertSame(
            ['--entry-naming=[dir]/[name]-[hash].[ext]', '--chunk-naming=lib/[name].[ext]'],
            (new BuildOptions(hashedNames: true, chunkNaming: 'lib/[name].[ext]'))->toArguments(),
        );
    }

    public function testRepeatableFlags(): void
    {
        $options = new BuildOptions(
            define: ['NODE_ENV="production"', 'DEBUG=false'],
            external: ['react', 'react-dom'],
            drop: ['console', 'debugger'],
        );

        self::assertSame([
            '--define=NODE_ENV="production"',
            '--define=DEBUG=false',
            '--external=react',
            '--external=react-dom',
            '--drop=console',
            '--drop=debugger',
        ], $options->toArguments());
    }

    public function testFullOptionSetMapsInStableOrder(): void
    {
        $options = new BuildOptions(
            outDir: 'dist',
            outFile: 'bundle.js',
            target: 'browser',
            format: 'esm',
            minify: true,
            minifySyntax: true,
            minifyWhitespace: true,
            minifyIdentifiers: true,
            splitting: true,
            sourcemap: 'linked',
            hashedNames: true,
            chunkNaming: 'chunks/[name]-[hash].[ext]',
            assetNaming: 'assets/[name]-[hash].[ext]',
            metafile: 'dist/meta.json',
            define: ['A=1'],
            external: ['vue'],
            banner: '/* banner */',
            footer: '// footer',
            drop: ['console'],
            watch: true,
        );

        self::assertSame([
            '--outdir=dist',
            '--outfile=bundle.js',
            '--target=browser',
            '--format=esm',
            '--minify',
            '--minify-syntax',
            '--minify-whitespace',
            '--minify-identifiers',
            '--splitting',
            '--sourcemap=linked',
            '--entry-naming=[dir]/[name]-[hash].[ext]',
            '--chunk-naming=chunks/[name]-[hash].[ext]',
            '--asset-naming=assets/[name]-[hash].[ext]',
            '--metafile=dist/meta.json',
            '--define=A=1',
            '--external=vue',
            '--banner=/* banner */',
            '--footer=// footer',
            '--drop=console',
            '--watch',
        ], $options->toArguments());
    }
}
