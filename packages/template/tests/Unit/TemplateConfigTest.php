<?php

declare(strict_types=1);

namespace PHPdot\Template\Tests\Unit;

use PHPdot\Template\TemplateConfig;
use PHPUnit\Framework\TestCase;

final class TemplateConfigTest extends TestCase
{
    public function test_defaults_are_production_safe(): void
    {
        $config = new TemplateConfig();

        self::assertSame(['__main__' => []], $config->paths);
        self::assertNull($config->cache);
        self::assertFalse($config->debug);
        self::assertTrue($config->strictVariables);
        self::assertSame('UTF-8', $config->charset);
        self::assertFalse($config->autoReload);
        self::assertSame('html', $config->autoescape);
    }

    public function test_constructor_accepts_all_fields(): void
    {
        $config = new TemplateConfig(
            paths: ['__main__' => ['/views'], 'admin' => ['/admin']],
            cache: '/tmp/twig',
            debug: true,
            strictVariables: false,
            charset: 'ISO-8859-1',
            autoReload: true,
            autoescape: false,
        );

        self::assertSame(['__main__' => ['/views'], 'admin' => ['/admin']], $config->paths);
        self::assertSame('/tmp/twig', $config->cache);
        self::assertTrue($config->debug);
        self::assertFalse($config->strictVariables);
        self::assertSame('ISO-8859-1', $config->charset);
        self::assertTrue($config->autoReload);
        self::assertFalse($config->autoescape);
    }
}
