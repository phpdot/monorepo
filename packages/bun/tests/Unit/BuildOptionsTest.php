<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Unit;

use PHPdot\Bun\Build\BuildOptions;
use PHPdot\Bun\Build\BuildSpec;
use PHPUnit\Framework\TestCase;

/**
 * Verifies every BuildOptions flag maps to the exact bun build argument, that defaults emit
 * nothing, and that the spec's withers thread each value through toOptions().
 */
final class BuildOptionsTest extends TestCase
{
    public function testDefaultsEmitNoArguments(): void
    {
        self::assertSame([], (new BuildOptions())->toArguments());
    }

    public function testEveryValueFlagMapsToItsArgument(): void
    {
        $options = new BuildOptions(
            root: 'resources/js',
            publicPath: 'https://cdn.example.com/build/',
            entryNaming: '[dir]/[name].[ext]',
            packages: 'external',
            loader: ['.svg:text', '.png:dataurl'],
            conditions: ['phpdot', 'development'],
            env: 'PUBLIC_*',
            metafileMd: '.phpdot/build/graph.md',
        );

        self::assertSame([
            '--root=resources/js',
            '--public-path=https://cdn.example.com/build/',
            '--entry-naming=[dir]/[name].[ext]',
            '--metafile-md=.phpdot/build/graph.md',
            '--packages=external',
            '--loader=.svg:text',
            '--loader=.png:dataurl',
            '--conditions=phpdot',
            '--conditions=development',
            '--env=PUBLIC_*',
        ], $options->toArguments());
    }

    public function testEveryBooleanFlagMapsToItsArgument(): void
    {
        $options = new BuildOptions(
            cssChunking: true,
            keepNames: true,
            rejectUnresolved: true,
            watch: true,
            noClearScreen: true,
        );

        self::assertSame([
            '--keep-names',
            '--css-chunking',
            '--reject-unresolved',
            '--watch',
            '--no-clear-screen',
        ], $options->toArguments());
    }

    public function testExplicitEntryNamingWinsOverTheHashedNamesPreset(): void
    {
        $options = new BuildOptions(hashedNames: true, entryNaming: '[name].[ext]');

        $args = $options->toArguments();

        self::assertContains('--entry-naming=[name].[ext]', $args);
        self::assertNotContains('--entry-naming=[dir]/[name]-[hash].[ext]', $args);
        self::assertContains('--chunk-naming=[name]-[hash].[ext]', $args, 'hashed chunk default still applies');
    }

    public function testTheSpecThreadsEveryNewValueThroughToOptions(): void
    {
        $options = (new BuildSpec())
            ->root('resources/js')
            ->publicPath('/build/')
            ->entryNaming('[name].[ext]')
            ->cssChunking()
            ->keepNames()
            ->rejectUnresolved()
            ->packages('bundle')
            ->loader('.svg:text')
            ->conditions('phpdot')
            ->env('inline')
            ->metafileMd('graph.md')
            ->noClearScreen()
            ->toOptions();

        self::assertSame('resources/js', $options->root);
        self::assertSame('/build/', $options->publicPath);
        self::assertSame('[name].[ext]', $options->entryNaming);
        self::assertTrue($options->cssChunking);
        self::assertTrue($options->keepNames);
        self::assertTrue($options->rejectUnresolved);
        self::assertSame('bundle', $options->packages);
        self::assertSame(['.svg:text'], $options->loader);
        self::assertSame(['phpdot'], $options->conditions);
        self::assertSame('inline', $options->env);
        self::assertSame('graph.md', $options->metafileMd);
        self::assertTrue($options->noClearScreen);
    }

    public function testWithersAreImmutable(): void
    {
        $base = new BuildSpec();
        $configured = $base->publicPath('/build/')->cssChunking()->loader('.svg:text');

        self::assertNotSame($base, $configured);
        self::assertNull($base->toOptions()->publicPath);
        self::assertFalse($base->toOptions()->cssChunking);
        self::assertSame([], $base->toOptions()->loader);
    }
}
