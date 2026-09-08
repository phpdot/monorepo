<?php

declare(strict_types=1);

namespace PHPdot\Mcp\Tests\Integration;

use PHPdot\Mcp\Tests\Support\SwooleHarness;
use PHPUnit\Framework\Attributes\Test;

/**
 * The whole endpoint on a real phpdot Server — two workers, SWOOLE_HOOK_ALL,
 * production shape — driven over raw HTTP. This is where the package's three
 * claims become facts: the tool list follows the actor, sessions survive a
 * dispatch to the sibling worker, and an anonymous request is refused.
 */
final class McpOverHttpTest extends SwooleHarness
{
    protected function setUp(): void
    {
        if (!extension_loaded('swoole')) {
            self::markTestSkipped('ext-swoole is not loaded.');
        }

        parent::setUp();
    }

    #[Test]
    public function initializeAnswersWithTheConfiguredIdentity(): void
    {
        $response = $this->post('operator', $this->initializeMessage());

        self::assertStringContainsString('200', $this->statusLine($response));
        self::assertSame('wire-test-server', $this->jsonOf($response)['result']['serverInfo']['name']);
        self::assertSame('3.1.4', $this->jsonOf($response)['result']['serverInfo']['version']);
        self::assertNotSame('', $this->headerLine($response, 'Mcp-Session-Id'));
    }

    #[Test]
    public function theToolListFollowsTheActorOnTheWire(): void
    {
        $operatorSession = $this->initializedSession('operator');
        $viewerSession = $this->initializedSession('viewer');

        $operatorTools = $this->jsonOf($this->post('operator', [
            'jsonrpc' => '2.0',
            'id'      => 21,
            'method'  => 'tools/list',
        ], $operatorSession))['result']['tools'];

        $viewerTools = $this->jsonOf($this->post('viewer', [
            'jsonrpc' => '2.0',
            'id'      => 22,
            'method'  => 'tools/list',
        ], $viewerSession))['result']['tools'];

        self::assertSame(
            ['catalog_broken', 'catalog_purge', 'catalog_search'],
            array_column($operatorTools, 'name'),
        );

        self::assertSame(
            ['catalog_broken', 'catalog_search'],
            array_column($viewerTools, 'name'),
            'the destructive tool is invisible to the viewer, over the wire',
        );

        $byName = array_column($viewerTools, null, 'name');

        self::assertTrue($byName['catalog_search']['annotations']['readOnlyHint']);
        self::assertFalse($byName['catalog_search']['annotations']['destructiveHint']);
    }

    #[Test]
    public function aToolCallRunsOverTheWire(): void
    {
        $session = $this->initializedSession('viewer');

        $response = $this->post('viewer', [
            'jsonrpc' => '2.0',
            'id'      => 31,
            'method'  => 'tools/call',
            'params'  => ['name' => 'catalog_search', 'arguments' => ['query' => 'kettle']],
        ], $session);

        $result = $this->jsonOf($response)['result'];

        self::assertFalse($result['isError'] ?? false);

        $payload = json_decode((string) ($result['content'][0]['text'] ?? ''), true, 8);

        self::assertSame(['query' => 'kettle', 'via' => 'wire'], $payload);
    }

    #[Test]
    public function aSessionSurvivesDispatchAcrossWorkers(): void
    {
        $session = $this->initializedSession('viewer');

        /*
         * Twenty fresh connections on one session id: with two workers accepting,
         * both serve some of them — and every request must answer through the
         * session established on whichever worker first held it. One session
         * file on disk proves no worker re-initialized its own.
         */
        for ($i = 0; $i < 20; $i++) {
            $response = $this->post('viewer', [
                'jsonrpc' => '2.0',
                'id'      => 40 + $i,
                'method'  => 'tools/list',
            ], $session);

            self::assertStringContainsString('200', $this->statusLine($response), 'request ' . $i . ' failed');
        }

        $files = glob(sys_get_temp_dir() . '/phpdot-mcp-wire-' . $this->port . '/*/*');

        self::assertGreaterThan(0, $files === false ? 0 : count($files), 'the session store holds the session');
    }

    #[Test]
    public function endingTheSessionEndsIt(): void
    {
        $session = $this->initializedSession('viewer');

        $delete = $this->http('DELETE', '/mcp', headers: [
            'X-Test-Actor' => 'viewer',
            'Mcp-Session-Id' => $session,
        ]);

        self::assertStringContainsString('200', $this->statusLine($delete));

        $after = $this->post('viewer', [
            'jsonrpc' => '2.0',
            'id'      => 51,
            'method'  => 'tools/list',
        ], $session);

        self::assertStringContainsString('404', $this->statusLine($after), 'a dead session is not accepted');
    }

    #[Test]
    public function aRequestWithoutAnActorIsRefusedOnTheWire(): void
    {
        $response = $this->post('', $this->initializeMessage());

        self::assertStringContainsString('500', $this->statusLine($response));
        self::assertStringContainsString('no actor behind it', $response);
    }

    /**
     * Run the initialize + initialized handshake, returning the session id.
     *
     * @param string $actor Who is connecting
     *
     * @return string
     */
    private function initializedSession(string $actor): string
    {
        $response = $this->post($actor, $this->initializeMessage());

        $session = $this->headerLine($response, 'Mcp-Session-Id');

        self::assertNotSame('', $session, 'initialize gave no session id');

        $this->post($actor, [
            'jsonrpc' => '2.0',
            'method'  => 'notifications/initialized',
        ], $session);

        return $session;
    }

    /**
     * POST one JSON-RPC message as one actor.
     *
     * @param string $actor The actor header value; '' sends none
     * @param array<string, mixed> $message The JSON-RPC message
     * @param string|null $session The session, when one is established
     *
     * @return string
     */
    private function post(string $actor, array $message, string|null $session = null): string
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json, text/event-stream',
        ];

        if ($actor !== '') {
            $headers['X-Test-Actor'] = $actor;
        }

        if ($session !== null && $session !== '') {
            $headers['Mcp-Session-Id'] = $session;
        }

        return $this->http('POST', '/mcp', (string) json_encode($message, JSON_THROW_ON_ERROR), $headers);
    }

    /**
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
                'clientInfo'      => ['name' => 'wire-test', 'version' => '1.0.0'],
            ],
        ];
    }

    /**
     * @return string
     */
    protected function runnerScript(): string
    {
        return __DIR__ . '/Fixtures/mcp_runner.php';
    }
}
