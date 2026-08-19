<?php

declare(strict_types=1);

namespace PHPdot\HttpMiddleware\Tests\Unit;

use PHPdot\Http\Factory\ResponseFactory;
use PHPdot\HttpMiddleware\Pipeline;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Pipeline contract: middlewares run in list order (first = outermost), any
 * middleware may short-circuit, an empty list is a pure passthrough, and one
 * shared instance serves interleaved requests without cross-talk — the
 * property Swoole coroutines depend on.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class PipelineTest extends TestCase
{
    /**
     * @var list<string> Execution trace shared by the tracing fixtures.
     */
    public static array $trace = [];

    protected function setUp(): void
    {
        self::$trace = [];
    }

    #[Test]
    public function middlewaresRunInListOrderAroundTheHandler(): void
    {
        $pipeline = new Pipeline(
            [$this->tracer('a'), $this->tracer('b')],
            $this->finalHandler('done'),
        );

        $response = $pipeline->handle($this->request('/x'));

        self::assertSame(['a:in', 'b:in', 'handler', 'b:out', 'a:out'], self::$trace);
        self::assertSame('done', (string) $response->getBody());
    }

    #[Test]
    public function aMiddlewareMayShortCircuit(): void
    {
        $blocker = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new ResponseFactory()->createResponse(403);
            }
        };

        $pipeline = new Pipeline(
            [$this->tracer('a'), $blocker, $this->tracer('never')],
            $this->finalHandler('unreached'),
        );

        $response = $pipeline->handle($this->request('/x'));

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(['a:in', 'a:out'], self::$trace, 'nothing after the short-circuit may run');
    }

    #[Test]
    public function anEmptyPipelineIsAPassthrough(): void
    {
        $pipeline = new Pipeline([], $this->finalHandler('bare'));

        self::assertSame('bare', (string) $pipeline->handle($this->request('/x'))->getBody());
    }

    #[Test]
    public function oneInstanceServesInterleavedRequestsWithoutCrossTalk(): void
    {
        $echo = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $factory = new ResponseFactory();

                return $factory->createResponse(200)
                    ->withBody($factory->createStream($request->getUri()->getPath()));
            }
        };

        $reentrant = new class implements MiddlewareInterface {
            public null|Pipeline $pipeline = null;

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                if ($request->getUri()->getPath() === '/outer' && $this->pipeline !== null) {
                    $inner = $this->pipeline->handle(
                        $request->withUri($request->getUri()->withPath('/inner')),
                    );

                    PipelineTest::$trace[] = 'inner:' . (string) $inner->getBody();
                }

                return $handler->handle($request);
            }
        };

        $pipeline = new Pipeline([$reentrant], $echo);
        $reentrant->pipeline = $pipeline;

        $outer = $pipeline->handle($this->request('/outer'));

        self::assertSame('/outer', (string) $outer->getBody(), 'the outer walk must be untouched by the nested one');
        self::assertSame(['inner:/inner'], self::$trace);
    }

    /**
     * A middleware that traces enter/exit around delegation.
     *
     * @param string $name Trace label
     *
     * @return MiddlewareInterface
     */
    private function tracer(string $name): MiddlewareInterface
    {
        return new class ($name) implements MiddlewareInterface {
            public function __construct(private readonly string $name) {}

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                PipelineTest::$trace[] = $this->name . ':in';
                $response = $handler->handle($request);
                PipelineTest::$trace[] = $this->name . ':out';

                return $response;
            }
        };
    }

    /**
     * A final handler tracing its run and answering with a fixed body.
     *
     * @param string $body Response body
     *
     * @return RequestHandlerInterface
     */
    private function finalHandler(string $body): RequestHandlerInterface
    {
        return new class ($body) implements RequestHandlerInterface {
            public function __construct(private readonly string $body) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                PipelineTest::$trace[] = 'handler';
                $factory = new ResponseFactory();

                return $factory->createResponse(200)->withBody($factory->createStream($this->body));
            }
        };
    }

    /**
     * A server request for the given path.
     *
     * @param string $path Request path
     *
     * @return ServerRequestInterface
     */
    private function request(string $path): ServerRequestInterface
    {
        return new ResponseFactory()->createServerRequest('GET', $path);
    }
}
