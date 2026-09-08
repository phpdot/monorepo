<?php

declare(strict_types=1);

/**
 * Integration-test server runner: the package's endpoint on a REAL phpdot Server,
 * two workers and SWOOLE_HOOK_ALL — the production shape — so the wire tests
 * prove what the doctrine claims: sessions cross workers, the tool list follows
 * the actor, and the endpoint refuses an anonymous request.
 *
 * The actor arrives on the X-Test-Actor header, which stands in for whatever the
 * surrounding pipeline of a real host would have resolved by now: `operator`
 * holds both fixture permissions, `viewer` one, anything else is nobody.
 *
 * Launched as a separate process by McpOverHttpTest; the port is argv[1].
 */

use PHPdot\Http\Factory\ResponseFactory;
use PHPdot\Mcp\Contract\ActorResolverInterface;
use PHPdot\Mcp\Contract\ToolActorInterface;
use PHPdot\Mcp\Server\McpConfig;
use PHPdot\Mcp\Server\McpEndpoint;
use PHPdot\Mcp\Server\McpGateway;
use PHPdot\Mcp\Tests\Support\Actor;
use PHPdot\Mcp\Tests\Support\Container;
use PHPdot\Mcp\Tests\Support\Scan\Clean\Catalog;
use PHPdot\Mcp\Tests\Support\Scan\Clean\CatalogTools;
use PHPdot\Mcp\Tool\ToolRegistry;
use PHPdot\Server\Config\HttpServerConfig;
use PHPdot\Server\Config\ServerConfig;
use PHPdot\Server\Http\HttpServer;
use PHPdot\Server\Server;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

$autoload = __DIR__;
while (!is_file($autoload . '/vendor/autoload.php') && dirname($autoload) !== $autoload) {
    $autoload = dirname($autoload);
}
require $autoload . '/vendor/autoload.php';

$port = (int) ($argv[1] ?? 0);

if ($port <= 0) {
    fwrite(STDERR, "usage: mcp_runner.php <port>\n");
    exit(1);
}

$factory = new ResponseFactory();

$config = new McpConfig(
    name: 'wire-test-server',
    version: '3.1.4',
    discoveryDirs: [dirname(__DIR__, 2) . '/Support/Scan/Clean'],
    sessionPath: sys_get_temp_dir() . '/phpdot-mcp-wire-' . $port,
);

$resolver = new class implements ActorResolverInterface {
    public function resolve(ServerRequestInterface $request): ToolActorInterface|null
    {
        return match ($request->getHeaderLine('X-Test-Actor')) {
            'operator' => new Actor(['catalog.view', 'catalog.purge']),
            'viewer'   => new Actor(['catalog.view']),
            default    => null,
        };
    }
};

$endpoint = new McpEndpoint(
    $resolver,
    new McpGateway(
        $factory,
        new ToolRegistry(
            new Container([
                CatalogTools::class => static fn(): CatalogTools => new CatalogTools(new Catalog('wire')),
            ]),
            $config,
        ),
        $config,
    ),
);

$notFound = $factory->createResponse(404);

$server = new Server(new ServerConfig(workerNum: 2, hookFlags: SWOOLE_HOOK_ALL));

$server->attach(new HttpServer($factory, new HttpServerConfig(host: '127.0.0.1', port: $port)));
$server->serve(new class ($endpoint, $notFound) implements RequestHandlerInterface {
    public function __construct(
        private readonly McpEndpoint $mcp,
        private readonly ResponseInterface $notFound,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getUri()->getPath() !== '/mcp') {
            return $this->notFound;
        }

        return $this->mcp->handle($request);
    }
});
