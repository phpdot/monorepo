<?php

declare(strict_types=1);

/**
 * Traceparent Middleware Test
 *
 * Pins the response-echo contract: the header names the request's root span —
 * read after the inner handler returns, when children have ended — and it is
 * present on a synthesized 500, the case where a client needs it most.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\HttpMiddleware\Tests\Unit;

use PHPdot\Container\Context\ArrayContextProvider;
use PHPdot\Contracts\Logs\WriterInterface;
use PHPdot\Http\Factory\ResponseFactory;
use PHPdot\HttpMiddleware\TraceparentMiddleware;
use PHPdot\Logs\CoreTracer;
use PHPdot\Logs\ScopeManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class TraceparentMiddlewareTest extends TestCase
{
    #[Test]
    public function echoesTheRootSpanTraceparentOnTheResponse(): void
    {
        [$tracer, $writer] = $this->tracer();
        $root = $tracer->span('GET /orders', 'server');

        $inner = new class ($tracer) implements RequestHandlerInterface {
            public function __construct(private readonly CoreTracer $tracer) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->tracer->span('db.query', 'client')->end();

                return (new ResponseFactory())->createResponse(200);
            }
        };

        $response = (new TraceparentMiddleware($tracer))->process(
            (new ResponseFactory())->createServerRequest('GET', '/orders'),
            $inner,
        );
        $root->end();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            $root->context()->toTraceparent(),
            $response->getHeaderLine('traceparent'),
            'the header names the span current at entry — the request root — not whatever span ran last inside',
        );
    }

    #[Test]
    public function aSynthesized500CarriesItToo(): void
    {
        [$tracer, $writer] = $this->tracer();
        $root = $tracer->span('GET /boom', 'server');

        $errorHandler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new ResponseFactory())->createResponse(500);
            }
        };

        $response = (new TraceparentMiddleware($tracer))->process(
            (new ResponseFactory())->createServerRequest('GET', '/boom'),
            $errorHandler,
        );
        $root->end();

        self::assertSame(500, $response->getStatusCode());
        self::assertSame($root->context()->toTraceparent(), $response->getHeaderLine('traceparent'));
    }

    #[Test]
    public function theHeaderIsValidW3cShapeAndCarriesTheLiveTrace(): void
    {
        [$tracer, $writer] = $this->tracer();
        $root = $tracer->span('GET /health', 'server');

        $response = $this->runMiddleware($tracer, status: 200);
        $root->end();

        $header = $response->getHeaderLine('traceparent');

        self::assertMatchesRegularExpression('/^00-[0-9a-f]{32}-[0-9a-f]{16}-0[01]$/', $header);
        self::assertStringContainsString($root->context()->traceId(), $header);

        $spanRecord = array_values(array_filter(
            $writer->records,
            static fn(array $record): bool => ($record['type'] ?? null) === 'span',
        ))[0];
        self::assertSame($spanRecord['trace_id'], $root->context()->traceId(), 'the echoed id resolves to the on-disk trace');
    }

    /**
     * @return array{PHPdot\Logs\CoreTracer, object}
     */
    private function tracer(): array
    {
        $writer = new class implements WriterInterface {
            /** @var list<array<string, mixed>> */
            public array $records = [];

            public function write(array $record): void
            {
                $this->records[] = $record;
            }
        };

        return [new CoreTracer(new ScopeManager(new ArrayContextProvider()), $writer), $writer];
    }

    private function runMiddleware(CoreTracer $tracer, int $status): ResponseInterface
    {
        $response = (new ResponseFactory())->createResponse($status);

        return (new TraceparentMiddleware($tracer))->process(
            (new ResponseFactory())->createServerRequest('GET', '/orders'),
            new class ($response) implements RequestHandlerInterface {
                public function __construct(private readonly ResponseInterface $response) {}

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->response;
                }
            },
        );
    }
}
