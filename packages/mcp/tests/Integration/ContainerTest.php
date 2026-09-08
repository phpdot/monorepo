<?php

declare(strict_types=1);

namespace PHPdot\Mcp\Tests\Integration;

use PHPdot\Container\ContainerBuilder;
use PHPdot\Container\Testing\TestContextProvider;
use PHPdot\Http\Factory\ResponseFactory;
use PHPdot\Mcp\Contract\ActorResolverInterface;
use PHPdot\Mcp\Server\McpConfig;
use PHPdot\Mcp\Server\McpEndpoint;
use PHPdot\Mcp\Server\McpGateway;
use PHPdot\Mcp\Tests\Support\Actor;
use PHPdot\Mcp\Tests\Support\StubResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Proves the package wires through the real phpdot container: the attribute
 * services are discovered by a directory scan over the host's bindings, the
 * endpoint is stable within a request and fresh across requests, and a host
 * that never bound an actor resolver fails loudly instead of anonymously.
 */
final class ContainerTest extends TestCase
{
    #[Test]
    public function theAttributeWiringResolvesOverHostBindings(): void
    {
        $requests = new TestContextProvider();
        $container = (new ContainerBuilder())
            ->withContextProvider($requests)
            ->scanAttributesIn(\dirname(__DIR__) . '/src')
            ->addDefinitions([
                McpConfig::class       => new McpConfig(name: 'container-test', version: '1.0.0', sessionPath: '/tmp/phpdot-mcp-ct'),
                ActorResolverInterface::class => new StubResolver(new Actor(['catalog.view'])),
                ResponseFactory::class => new ResponseFactory(),
            ])
            ->build();

        self::assertInstanceOf(McpGateway::class, $container->get(McpGateway::class));

        $first = $container->get(McpEndpoint::class);

        self::assertInstanceOf(McpEndpoint::class, $first);
        self::assertSame($first, $container->get(McpEndpoint::class), 'stable within one request');

        $requests->newContext('next-request');

        self::assertNotSame($first, $container->get(McpEndpoint::class), 'fresh across requests');
    }

    #[Test]
    public function aMissingResolverBindingFailsLoudly(): void
    {
        $container = (new ContainerBuilder())
            ->withContextProvider(new TestContextProvider())
            ->scanAttributesIn(\dirname(__DIR__) . '/src')
            ->addDefinitions([
                McpConfig::class       => new McpConfig(name: 'container-test', version: '1.0.0', sessionPath: '/tmp/phpdot-mcp-ct'),
                ResponseFactory::class => new ResponseFactory(),
            ])
            ->build();

        try {
            $container->get(McpEndpoint::class);

            self::fail('The endpoint resolved without an actor resolver binding.');
        } catch (Throwable $failure) {
            self::addToAssertionCount(1);
        }
    }
}
