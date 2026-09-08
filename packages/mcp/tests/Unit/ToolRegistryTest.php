<?php

declare(strict_types=1);

namespace PHPdot\Mcp\Tests\Unit;

use PHPdot\Mcp\Exception\ToolException;
use PHPdot\Mcp\Server\McpConfig;
use PHPdot\Mcp\Tests\Support\Actor;
use PHPdot\Mcp\Tests\Support\Container;
use PHPdot\Mcp\Tests\Support\Scan\Clean\Catalog;
use PHPdot\Mcp\Tests\Support\Scan\Clean\CatalogTools;
use PHPdot\Mcp\Tool\ToolArguments;
use PHPdot\Mcp\Tool\ToolRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ToolRegistryTest extends TestCase
{
    #[Test]
    public function discoveryFindsToolsWithTheirSchemasAndHints(): void
    {
        $registry = $this->registry();

        $all = $registry->all();

        self::assertSame(
            ['catalog_broken', 'catalog_purge', 'catalog_search'],
            array_keys($all),
            'tools are keyed and ordered by name',
        );

        $search = $all['catalog_search'];

        self::assertSame('catalog.view', $search->permission);
        self::assertSame(CatalogTools::class, $search->class);
        self::assertSame('search', $search->method);
        self::assertTrue($search->readOnly);
        self::assertTrue($search->idempotent);
        self::assertFalse($search->destructive);
        self::assertSame(
            ['type' => 'object', 'properties' => ['query' => ['type' => 'string']], 'required' => ['query']],
            $search->parameters,
        );

        self::assertTrue($all['catalog_purge']->destructive);
        self::assertFalse($all['catalog_purge']->readOnly);

        self::assertSame(
            ['type' => 'object', 'properties' => [], 'additionalProperties' => false],
            $all['catalog_purge']->parameters,
            'a tool with no schema method gets the empty object schema',
        );
    }

    #[Test]
    public function theListIsFilteredByTheActorsPermissions(): void
    {
        $registry = $this->registry();

        $operator = $registry->forActor(new Actor(['catalog.view', 'catalog.purge']));
        $viewer = $registry->forActor(new Actor(['catalog.view']));
        $nobody = $registry->forActor(new Actor([]));

        self::assertSame(['catalog_broken', 'catalog_purge', 'catalog_search'], $this->names($operator));
        self::assertSame(['catalog_broken', 'catalog_search'], $this->names($viewer));
        self::assertSame([], $this->names($nobody));
    }

    #[Test]
    public function callRunsTheToolThroughTheContainer(): void
    {
        $registry = $this->registry(marker: 'via-container');

        $answer = $registry->call('catalog_search', new ToolArguments(['query' => ' kettle ']), new Actor(['catalog.view']));

        self::assertSame(['query' => 'kettle', 'via' => 'via-container'], $answer);
    }

    #[Test]
    public function callRechecksThePermissionEvenWhenPreFiltered(): void
    {
        $registry = $this->registry();

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('You do not hold [catalog.purge], which [catalog_purge] requires.');

        $registry->call('catalog_purge', new ToolArguments([]), new Actor(['catalog.view']));
    }

    #[Test]
    public function anUnknownNameIsRefused(): void
    {
        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('No tool is named [catalog_nothing].');

        $this->registry()->call('catalog_nothing', new ToolArguments([]), new Actor(['catalog.view']));
    }

    #[Test]
    public function aNonArrayAnswerIsRefused(): void
    {
        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Tool [catalog_broken] answered with something other than an array.');

        $this->registry()->call('catalog_broken', new ToolArguments([]), new Actor(['catalog.view']));
    }

    #[Test]
    public function aDuplicatedNameIsABootFailure(): void
    {
        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Two tools claim the name [clashing_tool]');

        $this->registry(dir: 'Duplicate')->all();
    }

    #[Test]
    public function aMissingPermissionIsABootFailure(): void
    {
        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('declares no permission. Every tool states exactly one.');

        $this->registry(dir: 'Unpermissioned')->all();
    }

    #[Test]
    public function anAbsentDirectoryAnswersNoTools(): void
    {
        $registry = $this->registry(dir: 'DoesNotExist');

        self::assertSame([], $registry->all());
        self::assertSame([], $registry->forActor(new Actor(['catalog.view'])));
    }

    #[Test]
    public function anEmptyDiscoveryListIsLegal(): void
    {
        $registry = new ToolRegistry(
            new Container([]),
            new McpConfig(name: 'test', version: '1.0.0', sessionPath: '/tmp/mcp-test-sessions'),
        );

        self::assertSame([], $registry->all());
    }

    /**
     * @param string $dir Which fixture directory to scan
     * @param string $marker What the injected catalog service carries
     *
     * @return ToolRegistry
     */
    private function registry(string $dir = 'Clean', string $marker = 'catalog'): ToolRegistry
    {
        return new ToolRegistry(
            new Container([
                CatalogTools::class => static fn(): CatalogTools => new CatalogTools(new Catalog($marker)),
            ]),
            new McpConfig(
                name: 'test-server',
                version: '1.2.3',
                discoveryDirs: [__DIR__ . '/../Support/Scan/' . $dir],
                sessionPath: '/tmp/mcp-test-sessions',
            ),
        );
    }

    /**
     * @param list<\PHPdot\Mcp\Tool\ToolDescriptor> $tools
     *
     * @return list<string>
     */
    private function names(array $tools): array
    {
        $names = [];

        foreach ($tools as $tool) {
            $names[] = $tool->name;
        }

        return $names;
    }
}
