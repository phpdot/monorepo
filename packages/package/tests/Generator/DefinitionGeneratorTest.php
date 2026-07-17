<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Generator;

use PHPdot\Container\Scope;
use PHPdot\Package\Generator\DefinitionGenerator;
use PHPdot\Package\Scanner\PackageMeta;
use PHPdot\Package\Scanner\ScannedClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DefinitionGeneratorTest extends TestCase
{
    private DefinitionGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new DefinitionGenerator();
    }

    #[Test]
    public function it_generates_simple_definition(): void
    {
        $output = $this->generator->generate([
            new ScannedClass('App\\SimpleService', Scope::SINGLETON, [], [], null, 'app/pkg'),
        ]);

        self::assertStringContainsString('Scope::SINGLETON', $output);
        self::assertMatchesRegularExpression(
            '/\\\\App\\\\SimpleService::class\s*=>\s*new ScopedDefinition\(\s*scope:\s*Scope::SINGLETON,\s*\),/',
            $output,
        );
    }

    #[Test]
    public function it_generates_factory_with_params(): void
    {
        $output = $this->generator->generate([
            new ScannedClass('App\\MyService', Scope::SCOPED, ['depA' => 'App\\DepA', 'depB' => 'App\\DepB'], [], null, 'app/pkg'),
        ]);

        self::assertStringContainsString('Scope::SCOPED', $output);
        self::assertStringContainsString('depA: $c->get(\\App\\DepA::class)', $output);
        self::assertStringContainsString('depB: $c->get(\\App\\DepB::class)', $output);
        self::assertStringContainsString('new \\App\\MyService(', $output);
    }

    #[Test]
    public function it_generates_config_dto(): void
    {
        $output = $this->generator->generate([
            new ScannedClass('App\\AppConfig', Scope::SINGLETON, [], [], 'app', 'app/pkg'),
        ]);

        self::assertStringContainsString("->dto('app', \\App\\AppConfig::class)", $output);
        self::assertStringContainsString('\\PHPdot\\Config\\Configuration::class', $output);
    }

    #[Test]
    public function it_skips_classes_without_a_scope(): void
    {
        // An install-hook-only class carries no scope and gets no definition.
        $output = $this->generator->generate([
            new ScannedClass('App\\Installer', null, [], [], null, 'app/pkg', [], true),
        ]);

        self::assertStringNotContainsString('App\\Installer', $output);
    }

    #[Test]
    public function it_generates_interface_binding(): void
    {
        $output = $this->generator->generate([
            new ScannedClass('App\\MyImpl', Scope::SINGLETON, [], ['App\\MyInterface'], null, 'app/pkg'),
        ]);

        self::assertStringContainsString('\\App\\MyInterface::class', $output);
        self::assertStringContainsString('$c->get(\\App\\MyImpl::class)', $output);
    }

    #[Test]
    public function it_groups_by_package(): void
    {
        $output = $this->generator->generate([
            new ScannedClass('Vendor\\A', Scope::SINGLETON, [], [], null, 'vendor/alpha'),
            new ScannedClass('Vendor\\B', Scope::SINGLETON, [], [], null, 'vendor/beta'),
        ]);

        self::assertStringContainsString('vendor/alpha', $output);
        self::assertStringContainsString('vendor/beta', $output);
    }

    #[Test]
    public function it_generates_valid_php(): void
    {
        $output = $this->generator->generate([
            new ScannedClass('App\\Svc', Scope::SINGLETON, ['dep' => 'App\\Dep'], ['App\\Iface'], 'svc', 'app/pkg'),
        ]);

        $tokens = token_get_all($output);
        self::assertNotEmpty($tokens);
    }

    #[Test]
    public function it_generates_multiple_binds(): void
    {
        $output = $this->generator->generate([
            new ScannedClass('App\\Store', Scope::SINGLETON, [], ['App\\CacheIface', 'App\\StoreIface'], null, 'app/pkg'),
        ]);

        self::assertStringContainsString('\\App\\CacheIface::class', $output);
        self::assertStringContainsString('\\App\\StoreIface::class', $output);
    }

    #[Test]
    public function it_uses_correct_scope_strings(): void
    {
        $output = $this->generator->generate([
            new ScannedClass('A\\S', Scope::SINGLETON, [], [], null, 'a'),
            new ScannedClass('A\\C', Scope::SCOPED, [], [], null, 'a'),
            new ScannedClass('A\\T', Scope::TRANSIENT, [], [], null, 'a'),
        ]);

        self::assertStringContainsString('Scope::SINGLETON', $output);
        self::assertStringContainsString('Scope::SCOPED', $output);
        self::assertStringContainsString('Scope::TRANSIENT', $output);
    }

    #[Test]
    public function it_uses_leading_backslash(): void
    {
        $output = $this->generator->generate([
            new ScannedClass('App\\MyClass', Scope::SINGLETON, ['dep' => 'App\\Dep'], [], null, 'app/pkg'),
        ]);

        self::assertStringContainsString('\\App\\MyClass::class', $output);
        self::assertStringContainsString('\\App\\Dep::class', $output);
    }

    #[Test]
    public function it_handles_empty_input(): void
    {
        $output = $this->generator->generate([]);

        self::assertStringContainsString('return [', $output);
        self::assertStringContainsString('];', $output);

        $tokens = token_get_all($output);
        self::assertNotEmpty($tokens);
    }

    #[Test]
    public function it_emits_self_bindings_for_package_manager_and_manifest(): void
    {
        $output = $this->generator->generate([]);

        self::assertStringContainsString('\\PHPdot\\Package\\PackageManager::class', $output);
        self::assertStringContainsString('\\PHPdot\\Package\\Manifest::class', $output);
        self::assertStringContainsString('->manifest()', $output);
    }

    #[Test]
    public function self_bindings_appear_even_with_scanned_classes(): void
    {
        $output = $this->generator->generate([
            new ScannedClass('App\\Svc', Scope::SINGLETON, [], [], null, 'app/pkg'),
        ]);

        self::assertStringContainsString('\\PHPdot\\Package\\Manifest::class', $output);
        self::assertStringContainsString('\\PHPdot\\Package\\PackageManager::class', $output);
    }

    #[Test]
    public function it_has_professional_header(): void
    {
        $output = $this->generator->generate([
            new ScannedClass('App\\Svc', Scope::SINGLETON, [], [], null, 'app/pkg'),
        ]);

        self::assertStringContainsString('PHPdot Container Definitions', $output);
        self::assertStringContainsString('@generated   phpdot/package', $output);
        self::assertStringContainsString('@date', $output);
        self::assertStringContainsString('Do not edit', $output);
    }

    #[Test]
    public function it_uses_docblock_section_headers(): void
    {
        $packages = [
            'vendor/alpha' => new PackageMeta(
                name: 'vendor/alpha',
                description: 'Alpha package description.',
                url: 'https://github.com/vendor/alpha',
            ),
        ];

        $output = $this->generator->generate([
            new ScannedClass('Vendor\\A', Scope::SINGLETON, [], [], null, 'vendor/alpha'),
        ], $packages);

        self::assertStringContainsString('/**', $output);
        self::assertStringContainsString('vendor/alpha', $output);
        self::assertStringContainsString('Alpha package description.', $output);
        self::assertStringContainsString('@see https://github.com/vendor/alpha', $output);
        self::assertStringNotContainsString('// ───', $output);
    }
}
