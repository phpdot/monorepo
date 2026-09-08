<?php

declare(strict_types=1);

namespace PHPdot\Mcp\Tests\Unit;

use Mcp\Capability\Registry\ElementReference;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Schema\Tool;
use PHPdot\Mcp\Exception\ToolException;
use PHPdot\Mcp\Server\McpConfig;
use PHPdot\Mcp\Server\ToolReferenceHandler;
use PHPdot\Mcp\Tests\Support\Actor;
use PHPdot\Mcp\Tests\Support\Container;
use PHPdot\Mcp\Tests\Support\Scan\Clean\Catalog;
use PHPdot\Mcp\Tests\Support\Scan\Clean\CatalogTools;
use PHPdot\Mcp\Tool\ToolRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ToolReferenceHandlerTest extends TestCase
{
    #[Test]
    public function itRoutesTheCallIntoTheRegistryAsTheActor(): void
    {
        $handler = new ToolReferenceHandler($this->registry(), new Actor(['catalog.view']));

        $answer = $handler->handle($this->reference('catalog_search'), [
            'query'    => 'kettle',
            '_session' => 'sdk-internal',
            '_request' => ['the SDK' => 'own injectables'],
        ]);

        self::assertSame(['query' => 'kettle', 'via' => 'handler-test'], $answer);
    }

    #[Test]
    public function thePermissionIsStillRecheckedHere(): void
    {
        $handler = new ToolReferenceHandler($this->registry(), new Actor(['catalog.view']));

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('You do not hold [catalog.purge]');

        $handler->handle($this->reference('catalog_purge'), []);
    }

    #[Test]
    public function aReferenceThatIsNotAToolIsRefused(): void
    {
        $handler = new ToolReferenceHandler($this->registry(), new Actor(['catalog.view']));

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('This server serves tools only');

        $handler->handle(new ElementReference('strlen'), []);
    }

    /**
     * @return ToolRegistry
     */
    private function registry(): ToolRegistry
    {
        return new ToolRegistry(
            new Container([
                CatalogTools::class => static fn(): CatalogTools => new CatalogTools(new Catalog('handler-test')),
            ]),
            new McpConfig(
                name: 'test-server',
                version: '1.2.3',
                discoveryDirs: [__DIR__ . '/../Support/Scan/Clean'],
                sessionPath: sys_get_temp_dir() . '/phpdot-mcp-unit-' . uniqid(),
            ),
        );
    }

    /**
     * @param string $name The tool the SDK resolved the call to
     *
     * @return ToolReference
     */
    private function reference(string $name): ToolReference
    {
        return new ToolReference(
            new Tool(
                name: $name,
                title: null,
                inputSchema: ['type' => 'object', 'properties' => []],
                description: null,
                annotations: null,
            ),
            'strlen',
        );
    }
}
