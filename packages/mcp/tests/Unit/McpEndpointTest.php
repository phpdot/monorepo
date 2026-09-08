<?php

declare(strict_types=1);

namespace PHPdot\Mcp\Tests\Unit;

use PHPdot\Http\Factory\ResponseFactory;
use PHPdot\Http\Message\ServerRequest;
use PHPdot\Mcp\Exception\ConfigurationException;
use PHPdot\Mcp\Server\McpConfig;
use PHPdot\Mcp\Server\McpEndpoint;
use PHPdot\Mcp\Server\McpGateway;
use PHPdot\Mcp\Tests\Support\Actor;
use PHPdot\Mcp\Tests\Support\Container;
use PHPdot\Mcp\Tests\Support\Scan\Clean\Catalog;
use PHPdot\Mcp\Tests\Support\Scan\Clean\CatalogTools;
use PHPdot\Mcp\Tests\Support\StubResolver;
use PHPdot\Mcp\Tool\ToolRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class McpEndpointTest extends TestCase
{
    #[Test]
    public function aRequestWithoutAnActorIsRefused(): void
    {
        $endpoint = new McpEndpoint(new StubResolver(null), $this->gateway());

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('no actor behind it');

        $endpoint->handle($this->request([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'initialize',
            'params'  => ['protocolVersion' => '2025-06-18', 'capabilities' => [], 'clientInfo' => ['name' => 't', 'version' => '1']],
        ]));
    }

    #[Test]
    public function itAnswersInitializeAsTheResolvedActor(): void
    {
        $endpoint = new McpEndpoint(new StubResolver(new Actor(['catalog.view'])), $this->gateway());

        $response = $endpoint->handle($this->request([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'initialize',
            'params'  => ['protocolVersion' => '2025-06-18', 'capabilities' => [], 'clientInfo' => ['name' => 't', 'version' => '1']],
        ]));

        $decoded = json_decode((string) $response->getBody(), true, 32);

        self::assertSame('endpoint-test-server', $decoded['result']['serverInfo']['name']);
        self::assertNotSame('', $response->getHeaderLine('Mcp-Session-Id'));
    }

    /**
     * @param array<string, mixed> $message The JSON-RPC message to send
     *
     * @return ServerRequest
     */
    private function request(array $message): ServerRequest
    {
        $factory = new ResponseFactory();

        return new ServerRequest('POST', '/mcp')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json, text/event-stream')
            ->withBody($factory->createStream((string) json_encode($message, JSON_THROW_ON_ERROR)));
    }

    /**
     * @return McpGateway
     */
    private function gateway(): McpGateway
    {
        $factory = new ResponseFactory();

        $config = new McpConfig(
            name: 'endpoint-test-server',
            version: '1.0.0',
            discoveryDirs: [__DIR__ . '/../Support/Scan/Clean'],
            sessionPath: sys_get_temp_dir() . '/phpdot-mcp-unit-' . uniqid(),
        );

        return new McpGateway(
            $factory,
            new ToolRegistry(
                new Container([
                    CatalogTools::class => static fn(): CatalogTools => new CatalogTools(new Catalog('endpoint')),
                ]),
                $config,
            ),
            $config,
        );
    }
}
