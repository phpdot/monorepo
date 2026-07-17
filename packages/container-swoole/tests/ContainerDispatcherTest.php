<?php

declare(strict_types=1);
namespace PHPdot\Container\Swoole\Tests;

use PHPdot\Container\Swoole\ContainerDispatcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use stdClass;

final class ContainerDispatcherTest extends TestCase
{
    #[Test]
    public function resolvesTheHandlerPerRequestAndDelegates(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('get')->with(RequestHandlerInterface::class)->willReturn($handler);

        $dispatcher = new ContainerDispatcher($container, RequestHandlerInterface::class);

        self::assertSame($response, $dispatcher->handle($request));
    }

    #[Test]
    public function throwsWhenTheResolvedServiceIsNotAHandler(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn(new stdClass());

        $dispatcher = new ContainerDispatcher($container, RequestHandlerInterface::class);

        $this->expectException(RuntimeException::class);
        $dispatcher->handle($this->createStub(ServerRequestInterface::class));
    }
}
