<?php

declare(strict_types=1);

/**
 * Tracer Logger Test
 *
 * Pins the inbound bridge: every PSR-3 level reaches the engine at the same
 * level, PSR-3 exception objects become the canonical `_e()` shape, and a call
 * re-entered from inside the write path is dropped instead of recursing — the
 * PendingLog flush happens inside the guarded window because the handle is
 * discarded within log().
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Psr3Bridge\Tests;

use Closure;
use PHPdot\Container\Context\ArrayContextProvider;
use PHPdot\Contracts\Logs\WriterInterface;
use PHPdot\Logs\CoreTracer;
use PHPdot\Logs\ScopeManager;
use PHPdot\Psr3Bridge\TracerLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use RuntimeException;

final class TracerLoggerTest extends TestCase
{
    #[Test]
    #[DataProvider('psr3LevelProvider')]
    public function forwardsEveryPsr3LevelToTheEngineAtTheSameLevel(string $level): void
    {
        $writer = $this->capturingWriter();
        $logger = new TracerLogger($this->tracer($writer), 'db');

        $logger->log($level, 'the message', ['k' => 'v']);

        $record = $this->logsIn($writer->records)[0];
        self::assertSame($level, $record['level']);
        self::assertSame('the message', $record['message']);
        self::assertSame(['k' => 'v'], $record['context']);
        self::assertSame('db', $record['channel'], 'the configured channel is applied');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function psr3LevelProvider(): array
    {
        return [
            'debug' => [LogLevel::DEBUG],
            'info' => [LogLevel::INFO],
            'notice' => [LogLevel::NOTICE],
            'warning' => [LogLevel::WARNING],
            'error' => [LogLevel::ERROR],
            'critical' => [LogLevel::CRITICAL],
            'alert' => [LogLevel::ALERT],
            'emergency' => [LogLevel::EMERGENCY],
        ];
    }

    #[Test]
    public function convenienceMethodsDelegateToLog(): void
    {
        $writer = $this->capturingWriter();
        $logger = new TracerLogger($this->tracer($writer));

        $logger->warning('via the convenience method');

        self::assertSame('warning', $this->logsIn($writer->records)[0]['level']);
    }

    #[Test]
    public function anUnknownLevelFallsBackToInfo(): void
    {
        $writer = $this->capturingWriter();
        $logger = new TracerLogger($this->tracer($writer));

        $logger->log('verbose', 'x');

        self::assertSame('info', $this->logsIn($writer->records)[0]['level']);
    }

    #[Test]
    public function aPsr3ExceptionContextValueBecomesTheCanonicalShape(): void
    {
        $writer = $this->capturingWriter();
        $logger = new TracerLogger($this->tracer($writer));

        $logger->error('boom', ['exception' => new RuntimeException('gateway timeout', 504)]);

        $exception = $this->logsIn($writer->records)[0]['context']['exception'];
        self::assertSame(RuntimeException::class, $exception['class']);
        self::assertSame('gateway timeout', $exception['message']);
        self::assertSame(504, $exception['code']);
        self::assertArrayHasKey('file', $exception);
        self::assertArrayHasKey('line', $exception);
    }

    #[Test]
    public function linesAreCorrelatedToTheActiveSpan(): void
    {
        $writer = $this->capturingWriter();
        $tracer = $this->tracer($writer);
        $logger = new TracerLogger($tracer);

        $span = $tracer->span('unit.of.work', 'internal');
        $logger->info('inside the span');
        $span->end();

        $log = $this->logsIn($writer->records)[0];
        self::assertSame($span->context()->traceId(), $log['trace_id']);
        self::assertSame($span->context()->spanId(), $log['span_id']);
    }

    #[Test]
    public function aCallReEnteredFromInsideTheWritePathIsDroppedNotRecursed(): void
    {
        $writer = $this->capturingWriter();
        $tracer = $this->tracer($writer);

        $logger = null;
        $writer->onWrite = static function () use (&$logger): void {
            $logger?->error('re-entered from the write path');
        };

        $logger = new TracerLogger($tracer);
        $logger->warning('the original call');

        $logs = $this->logsIn($writer->records);
        self::assertCount(1, $logs, 'the re-entered call is dropped, never recursed');
        self::assertSame('the original call', $logs[0]['message']);
    }

    private function tracer(WriterInterface $writer): CoreTracer
    {
        return new CoreTracer(new ScopeManager(new ArrayContextProvider()), $writer);
    }

    /**
     * A writer that captures every exported record, optionally invoking a hook
     * mid-write so tests can simulate a PSR-3 consumer firing from inside the
     * write path.
     */
    private function capturingWriter(): WriterInterface
    {
        return new class implements WriterInterface {
            /** @var list<array<string, mixed>> */
            public array $records = [];

            /** @var Closure(): void|null */
            public null|Closure $onWrite = null;

            public function write(array $record): void
            {
                $this->records[] = $record;

                $this->onWrite?->__invoke();
            }
        };
    }

    /**
     * @param list<array<string, mixed>> $records
     *
     * @return list<array<string, mixed>>
     */
    private function logsIn(array $records): array
    {
        return array_values(array_filter(
            $records,
            static fn(array $record): bool => ($record['type'] ?? null) === 'log',
        ));
    }
}
