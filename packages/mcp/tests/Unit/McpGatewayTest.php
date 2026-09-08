<?php

declare(strict_types=1);

namespace PHPdot\Mcp\Tests\Unit;

use PHPdot\Http\Factory\ResponseFactory;
use PHPdot\Http\Message\ServerRequest;
use PHPdot\Mcp\Contract\ToolActorInterface;
use PHPdot\Mcp\Server\McpConfig;
use PHPdot\Mcp\Server\McpGateway;
use PHPdot\Mcp\Tests\Support\Actor;
use PHPdot\Mcp\Tests\Support\Container;
use PHPdot\Mcp\Tests\Support\MemorySessionStore;
use PHPdot\Mcp\Tests\Support\Scan\Clean\Catalog;
use PHPdot\Mcp\Tests\Support\Scan\Clean\CatalogTools;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * The full MCP lifecycle through the boundary, in-process: initialize handshake,
 * session continuity, per-actor tool listing, annotations on the wire, and a real
 * tool call — the exact JSON-RPC traffic a connected agent produces, minus the
 * socket. The socket itself is the integration suite's job.
 */
final class McpGatewayTest extends TestCase
{
    private ResponseFactory $factory;

    private McpGateway $gateway;

    protected function setUp(): void
    {
        $this->factory = new ResponseFactory();

        $config = new McpConfig(
            name: 'package-test-server',
            version: '9.9.9',
            discoveryDirs: [__DIR__ . '/../Support/Scan/Clean'],
            sessionPath: sys_get_temp_dir() . '/phpdot-mcp-unit-' . uniqid(),
        );

        $this->gateway = new McpGateway(
            $this->factory,
            new \PHPdot\Mcp\Tool\ToolRegistry(
                new Container([
                    CatalogTools::class => static fn(): CatalogTools => new CatalogTools(new Catalog('test-marker')),
                ]),
                $config,
            ),
            $config,
        );
    }

    #[Test]
    public function initializeAnswersWithTheConfiguredIdentity(): void
    {
        $response = $this->post($this->initializeMessage());

        self::assertSame(200, $response->getStatusCode());

        $result = $this->decode($response);

        self::assertSame('package-test-server', $result['result']['serverInfo']['name']);
        self::assertSame('9.9.9', $result['result']['serverInfo']['version']);
        self::assertNotSame('', $response->getHeaderLine('Mcp-Session-Id'));
    }

    #[Test]
    public function toolsListExposesExactlyTheActorsTools(): void
    {
        $session = $this->initializedSession(new Actor(['catalog.view']));

        $response = $this->post([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'method'  => 'tools/list',
        ], $session, new Actor(['catalog.view']));

        $tools = $this->decode($response)['result']['tools'];
        $names = array_column($tools, 'name');

        self::assertSame(['catalog_broken', 'catalog_search'], $names, 'the purge tool is invisible to this actor');

        $byName = array_column($tools, null, 'name');

        self::assertSame('catalog_search', $byName['catalog_search']['title']);
        self::assertTrue($byName['catalog_search']['annotations']['readOnlyHint']);
        self::assertTrue($byName['catalog_search']['annotations']['idempotentHint']);
        self::assertFalse($byName['catalog_search']['annotations']['destructiveHint']);
    }

    #[Test]
    public function anOperatorSeesTheDestructiveToolWithItsHint(): void
    {
        $operator = new Actor(['catalog.view', 'catalog.purge']);
        $session = $this->initializedSession($operator);

        $response = $this->post([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'method'  => 'tools/list',
        ], $session, $operator);

        $byName = array_column($this->decode($response)['result']['tools'], null, 'name');

        self::assertArrayHasKey('catalog_purge', $byName);
        self::assertTrue($byName['catalog_purge']['annotations']['destructiveHint']);
    }

    #[Test]
    public function toolCallsRunThroughTheRegistry(): void
    {
        $actor = new Actor(['catalog.view']);
        $session = $this->initializedSession($actor);

        $response = $this->post([
            'jsonrpc' => '2.0',
            'id'      => 3,
            'method'  => 'tools/call',
            'params'  => ['name' => 'catalog_search', 'arguments' => ['query' => 'kettle']],
        ], $session, $actor);

        $result = $this->decode($response)['result'];

        self::assertFalse($result['isError'] ?? false);

        $payload = json_decode((string) ($result['content'][0]['text'] ?? ''), true, 8);

        self::assertSame(['query' => 'kettle', 'via' => 'test-marker'], $payload);
    }

    #[Test]
    public function aToolOutsideTheActorsVocabularyDoesNotExist(): void
    {
        $viewer = new Actor(['catalog.view']);
        $session = $this->initializedSession($viewer);

        $response = $this->post([
            'jsonrpc' => '2.0',
            'id'      => 4,
            'method'  => 'tools/call',
            'params'  => ['name' => 'catalog_purge', 'arguments' => []],
        ], $session, $viewer);

        $decoded = $this->decode($response);

        self::assertSame(-32602, $decoded['error']['code']);
        self::assertSame('Tool not found: "catalog_purge".', $decoded['error']['message']);
        self::assertStringNotContainsString(
            'catalog.purge',
            $decoded['error']['message'],
            'the permission key behind the refusal must not leak — the tool simply is not there',
        );
    }

    #[Test]
    public function aBoundSessionStoreReplacesTheFiles(): void
    {
        $config = new McpConfig(name: 'package-test-server', version: '9.9.9', sessionPath: '');

        $gateway = new McpGateway(
            $this->factory,
            new \PHPdot\Mcp\Tool\ToolRegistry(new Container([]), $config),
            $config,
            new MemorySessionStore(),
        );

        $response = $this->initializeThrough($gateway);

        self::assertSame(200, $response->getStatusCode());
        self::assertNotSame('', $response->getHeaderLine('Mcp-Session-Id'));
    }

    #[Test]
    public function neitherAStoreNorAPathIsABootFailure(): void
    {
        $config = new McpConfig(name: 'package-test-server', version: '9.9.9', sessionPath: '');

        $this->expectException(\PHPdot\Mcp\Exception\ConfigurationException::class);
        $this->expectExceptionMessage('or a bound McpSessionStoreInterface');

        new McpGateway(
            $this->factory,
            new \PHPdot\Mcp\Tool\ToolRegistry(new Container([]), $config),
            $config,
        );
    }

    #[Test]
    public function aBodyAlreadyReadToEofStillInitializes(): void
    {
        $message = (string) json_encode($this->initializeMessage(), JSON_THROW_ON_ERROR);

        $request = new ServerRequest('POST', '/mcp')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json, text/event-stream')
            ->withBody($this->factory->createStream($message));

        /*
         * Read the body to its end, exactly as routing does before a request
         * reaches the gateway — without the rewind this answers -32700.
         */
        $request->getBody()->getContents();

        $response = $this->gateway->handle($request, new Actor(['catalog.view']));

        $decoded = $this->decode($response);

        self::assertSame('package-test-server', $decoded['result']['serverInfo']['name']);
    }

    /**
     * One initialize against an arbitrary gateway, as an empty-handed actor.
     *
     * @param McpGateway $gateway Who answers
     *
     * @return ResponseInterface
     */
    private function initializeThrough(McpGateway $gateway): ResponseInterface
    {
        $request = new ServerRequest('POST', '/mcp')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json, text/event-stream')
            ->withBody($this->factory->createStream(
                (string) json_encode($this->initializeMessage(), JSON_THROW_ON_ERROR),
            ));

        return $gateway->handle($request, new Actor([]));
    }

    /**
     * Run the initialize + initialized handshake, returning the session id.
     *
     * @param ToolActorInterface $actor Who is connecting
     *
     * @return string
     */
    private function initializedSession(ToolActorInterface $actor): string
    {
        $response = $this->post($this->initializeMessage(), null, $actor);

        $session = $response->getHeaderLine('Mcp-Session-Id');

        $this->post([
            'jsonrpc' => '2.0',
            'method'  => 'notifications/initialized',
        ], $session, $actor);

        return $session;
    }

    /**
     * POST one JSON-RPC message to the gateway as one actor.
     *
     * @param array<string, mixed> $message The JSON-RPC message
     * @param string|null $session The session, when one is established
     * @param ToolActorInterface|null $actor Who is asking; defaults to an empty-handed actor
     *
     * @return ResponseInterface
     */
    private function post(array $message, string|null $session = null, ToolActorInterface|null $actor = null): ResponseInterface
    {
        $request = new ServerRequest('POST', '/mcp')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json, text/event-stream')
            ->withBody($this->factory->createStream(
                (string) json_encode($message, JSON_THROW_ON_ERROR),
            ));

        if ($session !== null && $session !== '') {
            $request = $request->withHeader('Mcp-Session-Id', $session);
        }

        return $this->gateway->handle($request, $actor ?? new Actor([]));
    }

    /**
     * Decode a JSON or SSE-framed JSON-RPC response body.
     *
     * @param ResponseInterface $response What the gateway answered
     *
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        if (str_contains($response->getHeaderLine('Content-Type'), 'text/event-stream')) {
            preg_match_all('/^data: (.+)$/m', $body, $matches);

            $body = (string) end($matches[1]);
        }

        self::assertNotSame('', $body, 'the gateway answered an empty body');

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($body, true, 32, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * The client half of the initialize handshake.
     *
     * @return array<string, mixed>
     */
    private function initializeMessage(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'initialize',
            'params'  => [
                'protocolVersion' => '2025-06-18',
                'capabilities'    => [],
                'clientInfo'      => ['name' => 'phpdot-mcp-test', 'version' => '1.0.0'],
            ],
        ];
    }
}
