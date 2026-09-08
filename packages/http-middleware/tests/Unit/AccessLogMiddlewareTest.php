<?php

declare(strict_types=1);

/**
 * Access Log Middleware Test
 *
 * Pins the access-log contract: one line per request on the `http` channel at
 * the status-mapped level, the same field set stamped on the root span, and a
 * line still written when the pipeline itself blows up — the failure modes
 * that would otherwise be silent in the access log.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\HttpMiddleware\Tests\Unit;

use PHPdot\Container\Context\ArrayContextProvider;
use PHPdot\Contracts\Logs\WriterInterface;
use PHPdot\Http\Factory\ResponseFactory;
use PHPdot\HttpMiddleware\AccessLogMiddleware;
use PHPdot\Logs\CoreTracer;
use PHPdot\Logs\ScopeManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final class AccessLogMiddlewareTest extends TestCase
{
    #[Test]
    #[DataProvider('statusLevelProvider')]
    public function logsTheAccessLineAtTheStatusMappedLevel(int $status, string $level): void
    {
        [$tracer, $writer] = $this->tracer();
        $span = $tracer->span('GET /orders', 'server');

        $response = $this->runMiddleware($tracer, '/orders', status: $status);
        $span->end();

        self::assertSame($status, $response->getStatusCode());

        $log = $this->singleLog($writer);
        self::assertSame($level, $log['level']);
        self::assertSame('http', $log['channel']);
        self::assertSame("GET /orders {$status}", $log['message']);
        self::assertSame($status, $log['context']['http.status_code']);
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function statusLevelProvider(): array
    {
        return [
            '200 info' => [200, 'info'],
            '302 info' => [302, 'info'],
            '404 notice' => [404, 'notice'],
            '405 notice' => [405, 'notice'],
            '410 notice' => [410, 'notice'],
            '401 warning' => [401, 'warning'],
            '422 warning' => [422, 'warning'],
            '500 error' => [500, 'error'],
            '503 error' => [503, 'error'],
        ];
    }

    #[Test]
    public function stampsTheSameFieldSetOnTheRootSpan(): void
    {
        [$tracer, $writer] = $this->tracer();
        $span = $tracer->span('POST /orders', 'server');

        $request = (new ResponseFactory())->createServerRequest('POST', '/orders?flag=1', ['REMOTE_ADDR' => '203.0.113.7'])
            ->withHeader('User-Agent', 'net5-test/1.0')
            ->withHeader('Referer', 'https://example.net/start');

        $response = (new ResponseFactory())->createResponse(201);
        $response->getBody()->write('created');

        (new AccessLogMiddleware($tracer))->process($request, new class ($response) implements RequestHandlerInterface {
            public function __construct(private readonly ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        });
        $span->end();

        $attributes = $this->singleSpan($writer)['attributes'];
        self::assertSame('POST', $attributes['http.method']);
        self::assertSame('/orders', $attributes['url.path']);
        self::assertSame('flag=1', $attributes['url.query']);
        self::assertSame(201, $attributes['http.status_code']);
        self::assertSame(7, $attributes['http.response.bytes']);
        self::assertSame('203.0.113.7', $attributes['client.address']);
        self::assertSame('net5-test/1.0', $attributes['user.agent']);
        self::assertSame('https://example.net/start', $attributes['http.referer']);
        self::assertArrayHasKey('http.server.duration_ms', $attributes, 'the middleware window is namespaced; the span keeps its own duration_ms');
    }

    #[Test]
    public function emptyOptionalsAreOmittedNotWrittenBlank(): void
    {
        [$tracer, $writer] = $this->tracer();
        $span = $tracer->span('GET /health', 'server');

        $this->runMiddleware($tracer, '/health', status: 200);
        $span->end();

        $context = $this->singleLog($writer)['context'];
        foreach (['url.query', 'client.address', 'user.agent', 'http.referer'] as $absent) {
            self::assertArrayNotHasKey($absent, $context);
        }

        self::assertSame(0, $context['http.response.bytes'], 'an empty body is a value (0 bytes), not an absent one');
    }

    #[Test]
    public function aPipelineThatBlowsUpStillGetsItsLineAndTheThrowableEscapes(): void
    {
        [$tracer, $writer] = $this->tracer();
        $span = $tracer->span('GET /boom', 'server');

        $throwing = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('past the error handler');
            }
        };

        $caught = null;

        try {
            (new AccessLogMiddleware($tracer))->process(
                (new ResponseFactory())->createServerRequest('GET', '/boom'),
                $throwing,
            );
        } catch (RuntimeException $caught) {
        } finally {
            $span->end();
        }

        self::assertNotNull($caught, 'the throwable must escape, never be swallowed');

        $log = $this->singleLog($writer);
        self::assertSame('error', $log['level']);
        self::assertSame('GET /boom 500', $log['message'], 'every request that entered gets exactly one line');
        self::assertSame(500, $log['context']['http.status_code']);
    }

    #[Test]
    public function theAccessLineCorrelatesToTheRootSpan(): void
    {
        [$tracer, $writer] = $this->tracer();
        $span = $tracer->span('GET /orders', 'server');

        $this->runMiddleware($tracer, '/orders', status: 200);
        $span->end();

        $log = $this->singleLog($writer);
        $spanRecord = $this->singleSpan($writer);

        self::assertSame($spanRecord['trace_id'], $log['trace_id']);
        self::assertSame($spanRecord['span_id'], $log['span_id']);
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

    private function runMiddleware(CoreTracer $tracer, string $path, int $status): ResponseInterface
    {
        $response = (new ResponseFactory())->createResponse($status);

        return (new AccessLogMiddleware($tracer))->process(
            (new ResponseFactory())->createServerRequest('GET', $path),
            new class ($response) implements RequestHandlerInterface {
                public function __construct(private readonly ResponseInterface $response) {}

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->response;
                }
            },
        );
    }

    /**
     * @param object $writer The capturing writer from tracer().
     *
     * @return array<string, mixed>
     */
    private function singleLog(object $writer): array
    {
        $logs = array_values(array_filter(
            $writer->records,
            static fn(array $record): bool => ($record['type'] ?? null) === 'log',
        ));

        self::assertCount(1, $logs);

        return $logs[0];
    }

    /**
     * @param object $writer The capturing writer from tracer().
     *
     * @return array<string, mixed>
     */
    private function singleSpan(object $writer): array
    {
        $spans = array_values(array_filter(
            $writer->records,
            static fn(array $record): bool => ($record['type'] ?? null) === 'span',
        ));

        self::assertCount(1, $spans);

        return $spans[0];
    }
}
