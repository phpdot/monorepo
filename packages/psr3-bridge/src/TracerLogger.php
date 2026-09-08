<?php

declare(strict_types=1);

/**
 * Tracer Logger
 *
 * The inbound PSR-3 bridge: any code that type-hints `Psr\Log\LoggerInterface`
 * (this ecosystem's event, database, rabbitmq, and error-handler packages, or
 * third-party libraries) logs through the observability engine, trace- and
 * span-correlated, with channel routing and `->secure()` available to native
 * tracer callers. {@see Psr3Writer} is the outbound peer — engine records onto
 * a PSR-3 handler stack.
 *
 * Applications bind it explicitly:
 *
 *   $builder->add(LoggerInterface::class, fn ($c) => new TracerLogger(
 *       $c->get(TracerInterface::class),
 *   ))->singleton();
 *
 * It carries no `#[Binds]` on purpose: binding `LoggerInterface` by mere
 * installation would silently redefine logging for every application and arm a
 * container cycle (`Psr3Writer` resolved from the container would pull this
 * logger, which pulls the tracer, which pulls that writer).
 *
 * The no-loop rule this guard enforces: never hand this logger to a `Psr3Writer`
 * that the tracer exports to. A re-entrant call — a PSR-3 consumer logging from
 * inside a writer's write path — is dropped rather than recursing; without the
 * guard the cycle is unbounded (a mis-wired stack was measured eleven thousand
 * frames deep before the memory limit).
 *
 * `secure()` is unreachable from the PSR-3 side by design: encryption is a
 * tracer-API capability, and Psr3Writer applies it to engine records on the
 * way out. Sensitive logging stays on the tracer.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Psr3Bridge;

use PHPdot\Container\Attribute\Singleton;
use PHPdot\Contracts\Logs\TracerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;
use Throwable;

#[Singleton]
final class TracerLogger implements LoggerInterface
{
    /**
     * The channel-scoped engine every call is forwarded to.
     */
    private readonly TracerInterface $tracer;

    /**
     * Guards against re-entrancy: true while a call is being forwarded to the
     * tracer, so a PSR-3 consumer invoked from inside the write path is dropped
     * instead of recursing through the writer.
     */
    private bool $forwarding = false;

    /**
     * Create a PSR-3 facade over the tracer.
     *
     * @param TracerInterface $tracer The engine every call is forwarded to.
     * @param string $channel Channel tag for the emitted lines (e.g. 'event', 'db').
     */
    public function __construct(
        TracerInterface $tracer,
        string $channel = 'app',
    ) {
        $this->tracer = $tracer->channel($channel);
    }

    /**
     * Forward one PSR-3 call to the tracer at the same level.
     *
     * A PSR-3 `exception` context value is converted to the engine's canonical
     * `_e()` shape (class, message, code, file, line) so every exception line in
     * the output carries the same structure regardless of its origin. Unknown
     * level tokens fall back to info, mirroring {@see Psr3Writer}.
     *
     * @param mixed $level A PSR-3 level word; anything unrecognized falls back to info.
     * @param string|Stringable $message
     *
     * @return void
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        if ($this->forwarding) {
            return;
        }

        $this->forwarding = true;

        try {
            $context = $this->normalizeContext($context);

            if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
                $context['exception'] = _e($context['exception']);
            }

            $text = (string) $message;

            match ($level) {
                LogLevel::DEBUG => $this->tracer->debug($text, $context),
                LogLevel::INFO => $this->tracer->info($text, $context),
                LogLevel::NOTICE => $this->tracer->notice($text, $context),
                LogLevel::WARNING => $this->tracer->warning($text, $context),
                LogLevel::ERROR => $this->tracer->error($text, $context),
                LogLevel::CRITICAL => $this->tracer->critical($text, $context),
                LogLevel::ALERT => $this->tracer->alert($text, $context),
                LogLevel::EMERGENCY => $this->tracer->emergency($text, $context),
                default => $this->tracer->info($text, $context),
            };
        } finally {
            $this->forwarding = false;
        }
    }

    /**
     * @param string|Stringable $message
     *
     * @return void
     */
    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * @param string|Stringable $message
     *
     * @return void
     */
    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * @param string|Stringable $message
     *
     * @return void
     */
    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * @param string|Stringable $message
     *
     * @return void
     */
    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * @param string|Stringable $message
     *
     * @return void
     */
    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * @param string|Stringable $message
     *
     * @return void
     */
    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * @param string|Stringable $message
     *
     * @return void
     */
    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * @param string|Stringable $message
     *
     * @return void
     */
    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Normalize PSR-3 context to the engine's string-keyed shape — PHP arrays
     * may carry integer keys, the record context requires strings.
     *
     * @param array<mixed> $context
     *
     * @return array<string, mixed>
     */
    private function normalizeContext(array $context): array
    {
        $normalized = [];

        foreach ($context as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }
}
