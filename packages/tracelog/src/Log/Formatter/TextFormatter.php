<?php

declare(strict_types=1);

/**
 * Text Formatter
 *
 * Human-readable log formatter for development environments.
 * Output format: [2026-04-02 12:00:00.123] auth.INFO: message {"context":...} [trace:abc span:xyz]
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Log\Formatter;

final class TextFormatter implements FormatterInterface
{
    /**
     * Format a log record as a human-readable text line.
     *
     * @param array<string, mixed> $record The log record to format
     *
     * @return string Formatted text log entry with trailing newline
     */
    public function format(array $record): string
    {
        $timestamp = $this->extractString($record, 'timestamp');

        if (str_contains($timestamp, 'T')) {
            $timestamp = str_replace('T', ' ', $timestamp);
            $plusPos = strrpos($timestamp, '+');
            if ($plusPos !== false) {
                $timestamp = substr($timestamp, 0, $plusPos);
            }
        }

        $channel   = $this->extractString($record, 'channel', 'app');
        $levelName = $this->extractString($record, 'level_name', 'DEBUG');
        $message   = $this->extractString($record, 'message');

        /**
         * @var array<mixed> $context
         */
        $context = is_array($record['context'] ?? null) ? $record['context'] : [];
        $encoded = $context !== []
            ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR)
            : '{}';
        $contextJson = is_string($encoded) ? $encoded : '{}';

        $traceId = $this->extractString($record, 'trace_id');
        $spanId  = $this->extractString($record, 'span_id');

        $tracePart = '';
        if ($traceId !== '' || $spanId !== '') {
            $parts = [];
            if ($traceId !== '') {
                $parts[] = 'trace:' . $traceId;
            }
            if ($spanId !== '') {
                $parts[] = 'span:' . $spanId;
            }
            $tracePart = ' [' . implode(' ', $parts) . ']';
        }

        return sprintf(
            '[%s] %s.%s: %s %s%s',
            $timestamp,
            $channel,
            $levelName,
            $message,
            $contextJson,
            $tracePart,
        ) . "\n";
    }

    /**
     * Extract a string value from a record array with a default fallback.
     *
     * @param array<string, mixed> $record The log record
     * @param string $key The key to extract
     * @param string $default The default value if key is missing or not a string
     *
     * @return string The extracted string value
     */
    private function extractString(array $record, string $key, string $default = ''): string
    {
        $value = $record[$key] ?? $default;

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $default;
    }
}
