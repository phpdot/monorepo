<?php

declare(strict_types=1);

/**
 * TraceLog Writer
 *
 * The rich, encrypted-file backend for the phpdot observability engine. It
 * implements the engine's {@see WriterInterface} export boundary: it owns no trace
 * identity and mints no ids — it only takes an already-correlated record
 * (a log line or a finished span snapshot, as a flat `array<string, mixed>`),
 * normalizes it into the shape the channel handlers/formatters expect, and
 * writes it to the per-channel {@see StreamHandler} via the {@see ChannelManager}.
 *
 * Normalization maps the engine record onto the handler record shape:
 *   - `timestamp` float (microtime) -> ISO-8601 string,
 *   - `level` PSR string -> integer level + `level_name`,
 *   - the record `type` ('log' | 'span') is written onto the line, so the
 *     split survives on disk — no duck-typing context keys to tell them apart,
 *   - every record routes to its own `channel` field's stream, defaulting to `app`.
 *
 * Trace correlation fields (`trace_id`, `span_id`) are always written in
 * plaintext at the top level so the output stays queryable.
 *
 * A record flagged sensitive (`secure`/`sensitive`) has its message AND context
 * encrypted together with the {@see ChaChaEncryptor}. This is fail-closed: a
 * sensitive record that cannot be protected (no encryptor, or encryption fails)
 * is dropped, never written in plaintext. Export never throws — a failure in the
 * write path must not crash the application or the coroutine-end span flush.
 *
 * Stateless singleton; {@see Binds} makes it the default {@see WriterInterface}
 * whenever tracelog is installed — composer emits installed packages in
 * alphabetical order, so this binding always lands after (and therefore replaces)
 * the engine's NullWriter.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Writer;

use DateTimeImmutable;
use PHPdot\Container\Attribute\Binds;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\Contracts\Logs\EncryptorInterface;
use PHPdot\Contracts\Logs\WriterInterface;
use PHPdot\TraceLog\Encryption\ChaChaEncryptor;
use PHPdot\TraceLog\Log\Channel\ChannelManager;
use PHPdot\TraceLog\Log\Formatter\FormatterInterface;
use PHPdot\TraceLog\Log\Formatter\JsonFormatter;
use PHPdot\TraceLog\Log\Formatter\TextFormatter;
use PHPdot\TraceLog\Log\LogLevel;
use PHPdot\TraceLog\TraceLogConfig;
use stdClass;
use Throwable;

#[Singleton]
#[Binds(WriterInterface::class)]
final class TraceLogWriter implements WriterInterface
{
    /**
     * Channel router, derived from the configured base path, formatter, and gates.
     */
    private readonly ChannelManager $channelManager;

    /**
     * Master switch from configuration — false discards every record at the writer.
     */
    private readonly bool $enabled;

    /**
     * Protects sensitive records: injected override, the configured key, or none.
     */
    private readonly null|EncryptorInterface $encryptor;

    /**
     * Build the writer from the application's `config/tracelog.php`.
     *
     * The channel manager is derived from the DTO, so an install needs no binding
     * closure. A configured `encryptionKey` is validated here by constructing the
     * encryptor: a malformed key fails the boot loudly rather than dropping every
     * secure record silently for the life of the process. An explicitly injected
     * encryptor wins over the configured key.
     *
     * @param TraceLogConfig $config The hydrated application configuration.
     * @param EncryptorInterface|null $encryptor Optional encryptor override for sensitive records.
     */
    public function __construct(
        TraceLogConfig $config,
        null|EncryptorInterface $encryptor = null,
    ) {
        $this->channelManager = new ChannelManager(
            $config->basePath,
            self::formatter($config->defaultFormatter),
            $config->minLevel,
            $config->maxChannels,
        );

        $this->enabled = $config->enabled;

        $this->encryptor = $encryptor
            ?? ($config->encryptionKey === null ? null : new ChaChaEncryptor($config->encryptionKey));
    }

    /**
     * Export a single record — a log line or a finished span snapshot.
     *
     * Export never crashes the caller or the coroutine-end flush: a sensitive
     * record that cannot be protected is dropped (fail-closed), and any
     * unexpected failure is swallowed rather than thrown.
     *
     * @param array<string, mixed> $record The engine record to export.
     *
     * @return void
     */
    public function write(array $record): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            $isSpan = ($record['type'] ?? null) === 'span';

            $normalized = $isSpan
                ? $this->normalizeSpan($record)
                : $this->normalizeLog($record);

            if ($normalized === null) {
                return;
            }

            $this->channelManager->getHandler($this->channelName($record))->handle($normalized);
        } catch (Throwable) {
        }
    }

    /**
     * Normalize an engine log record into the handler/formatter record shape.
     *
     * @param array<string, mixed> $record The engine log record.
     *
     * @return array<string, mixed>|null The normalized record, or null if dropped (fail-closed).
     */
    private function normalizeLog(array $record): null|array
    {
        $level   = LogLevel::fromPsr($this->toString($record['level'] ?? null, 'debug'));
        $message = $this->toString($record['message'] ?? null);
        $context = $this->toArray($record['context'] ?? null);

        $protected = $this->protect($message, $context, $this->isSensitive($record));

        if ($protected === null) {
            return null;
        }

        return [
            'timestamp'  => $this->toIso($this->toFloat($record['timestamp'] ?? null)),
            'level'      => $level,
            'level_name' => LogLevel::name($level),
            'message'    => $protected['message'],
            'type'       => 'log',
            'channel'    => $this->channelName($record),
            'trace_id'   => $this->toString($record['trace_id'] ?? null),
            'span_id'    => $this->toString($record['span_id'] ?? null),
            'context'    => $this->asMap($protected['context']),
        ];
    }

    /**
     * Normalize a finished span snapshot into the handler/formatter record shape.
     *
     * The span name becomes the message; span timing/status/attributes/events are
     * carried in the context so the formatter renders the full snapshot.
     *
     * @param array<string, mixed> $record The engine span record.
     *
     * @return array<string, mixed>|null The normalized record, or null if dropped (fail-closed).
     */
    private function normalizeSpan(array $record): null|array
    {
        $name   = $this->toString($record['name'] ?? null, 'span');
        $status = $this->toString($record['status'] ?? null);
        $level  = strtolower($status) === 'error' ? LogLevel::ERROR : LogLevel::INFO;

        $endedAt   = $this->toFloat($record['ended_at'] ?? null);
        $startedAt = $this->toFloat($record['started_at'] ?? null);
        $stamp     = $endedAt > 0.0 ? $endedAt : $startedAt;

        $context = [
            'parent_span_id' => $this->toParentId($record['parent_span_id'] ?? null),
            'kind'           => $this->toString($record['kind'] ?? null),
            'started_at'     => $startedAt,
            'ended_at'       => $endedAt,
            'duration_ms'    => $this->toFloat($record['duration_ms'] ?? null),
            'status'         => $status,
            'status_message' => $this->toString($record['status_message'] ?? null),
            'attributes'     => $this->asMap($this->toArray($record['attributes'] ?? null)),
            'events'         => $this->toArray($record['events'] ?? null),
        ];

        $protected = $this->protect($name, $context, $this->isSensitive($record));

        if ($protected === null) {
            return null;
        }

        return [
            'timestamp'  => $this->toIso($stamp),
            'level'      => $level,
            'level_name' => LogLevel::name($level),
            'message'    => $protected['message'],
            'type'       => 'span',
            'channel'    => $this->channelName($record),
            'trace_id'   => $this->toString($record['trace_id'] ?? null),
            'span_id'    => $this->toString($record['span_id'] ?? null),
            'context'    => $protected['context'],
        ];
    }

    /**
     * Determine whether a record is marked sensitive and must be encrypted.
     *
     * @param array<string, mixed> $record The record to inspect.
     *
     * @return bool True if the record carries a truthy `secure` or `sensitive` marker.
     */
    private function isSensitive(array $record): bool
    {
        return ($record['secure'] ?? false) === true
            || ($record['sensitive'] ?? false) === true;
    }

    /**
     * Apply fail-closed protection to a record's message and context.
     *
     * For a non-sensitive record the message and context pass through unchanged.
     * For a sensitive record the message and context are encrypted together; if no
     * encryptor is configured or encryption fails, null is returned so the caller
     * drops the record rather than writing plaintext. Message and context are
     * encrypted together — structured context is where the secrets live.
     *
     * @param string $message The plaintext message.
     * @param array<array-key, mixed> $context The plaintext context.
     * @param bool $sensitive Whether the record must be protected.
     *
     * @return array{message: string, context: array<array-key, mixed>}|null
     */
    private function protect(string $message, array $context, bool $sensitive): null|array
    {
        if (!$sensitive) {
            return ['message' => $message, 'context' => $context];
        }

        if ($this->encryptor === null) {
            return null;
        }

        try {
            $payload = json_encode(
                ['message' => $message, 'context' => $context],
                JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE,
            );

            $ciphertext = $this->encryptor->encrypt($payload);
        } catch (Throwable) {
            return null;
        }

        return ['message' => $ciphertext, 'context' => ['encrypted' => true]];
    }

    /**
     * Convert a microtime float into an ISO-8601 timestamp string.
     *
     * Formatting uses %.6F because it is locale-independent — %f honors
     * LC_NUMERIC and could emit a comma that breaks the 'U.u' parse.
     *
     * @param float $timestamp Seconds since the epoch (microtime), or <= 0 for "now".
     *
     * @return string ISO-8601 timestamp with microseconds and offset.
     */
    private function toIso(float $timestamp): string
    {
        if ($timestamp <= 0.0) {
            return (new DateTimeImmutable())->format('Y-m-d\TH:i:s.uP');
        }

        $parsed = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $timestamp));

        $stamp = $parsed !== false ? $parsed : new DateTimeImmutable();

        return $stamp->format('Y-m-d\TH:i:s.uP');
    }

    /**
     * Coerce a mixed value into a string, falling back to a default.
     *
     * @param mixed $value The value to coerce.
     * @param string $default The fallback when the value is not stringable scalar.
     *
     * @return string The coerced string.
     */
    private function toString(mixed $value, string $default = ''): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * Coerce a mixed value into a float, falling back to 0.0.
     *
     * @param mixed $value The value to coerce.
     *
     * @return float The coerced float.
     */
    private function toFloat(mixed $value): float
    {
        if (is_float($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return 0.0;
    }

    /**
     * Coerce a mixed value into an array, falling back to an empty array.
     *
     * @param mixed $value The value to coerce.
     *
     * @return array<array-key, mixed> The coerced array.
     */
    private function toArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * Keep a schema-owned map field a stable JSON type.
     *
     * PHP renders an empty array as `[]`, so a map that serializes as `{}`
     * when populated and `[]` when empty changes JSON type with its content —
     * breaking typed ingestion downstream. Only the schema's own maps (log
     * context, span attributes) are cast; user-owned values keep their shapes,
     * and genuine lists (`events`) stay lists.
     *
     * @param array<array-key, mixed> $value The map to stabilize.
     *
     * @return array<array-key, mixed>|object The map, or an empty object.
     */
    private function asMap(array $value): array|object
    {
        return $value === [] ? new stdClass() : $value;
    }

    /**
     * A span's parent id, or null at a trace root.
     *
     * An empty string would claim a parent exists with an empty id; null is
     * the honest "no parent" and matches the engine's nullable export.
     *
     * @param mixed $value The raw parent id from the engine record.
     *
     * @return string|null The parent id, or null when the span is a root.
     */
    private function toParentId(mixed $value): null|string
    {
        $parent = $this->toString($value);

        return $parent === '' ? null : $parent;
    }

    /**
     * The channel a record routes to — its `channel` field, or 'app' by default.
     *
     * @param array<string, mixed> $record The engine record.
     *
     * @return string The channel name.
     */
    private function channelName(array $record): string
    {
        return $this->toString($record['channel'] ?? null, 'app');
    }

    /**
     * Build the configured default line format for every channel.
     *
     * @param string $name The validated formatter name from configuration.
     *
     * @return FormatterInterface
     */
    private static function formatter(string $name): FormatterInterface
    {
        return match ($name) {
            'text' => new TextFormatter(),
            default => new JsonFormatter(),
        };
    }
}
