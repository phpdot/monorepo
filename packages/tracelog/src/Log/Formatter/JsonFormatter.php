<?php

declare(strict_types=1);

/**
 * JSON Formatter
 *
 * Formats log records as single-line JSON entries.
 * Trace fields (trace_id, span_id, channel, tags) are promoted to the top level.
 * Remaining context is nested under the 'context' key.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Log\Formatter;

final class JsonFormatter implements FormatterInterface
{
    /**
     * Fields promoted from context to the top level of the JSON output.
     *
     * @var list<string>
     */
    private const array PROMOTED_FIELDS = [
        'trace_id',
        'span_id',
        'channel',
        'tags',
    ];

    /**
     * Format a log record as a single JSON line.
     *
     * Never throws from the logging path: malformed UTF-8 is substituted and
     * partial output tolerated, so an un-encodable context value cannot crash
     * the application.
     *
     * @param array<string, mixed> $record The log record to format
     *
     * @return string JSON-encoded log entry with trailing newline
     */
    public function format(array $record): string
    {
        /**
         * @var array<string, mixed> $context
         */
        $context = $record['context'] ?? [];

        $output = [
            'timestamp'  => $record['timestamp'] ?? '',
            'level'      => $record['level'] ?? 0,
            'level_name' => $record['level_name'] ?? '',
            'message'    => $record['message'] ?? '',
        ];

        foreach (self::PROMOTED_FIELDS as $field) {
            if (array_key_exists($field, $record)) {
                $output[$field] = $record[$field];
            }
        }

        $output['context'] = $context;

        $json = json_encode(
            $output,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR,
        );

        return ($json !== false ? $json : '{"level_name":"ERROR","message":"log record could not be encoded"}') . "\n";
    }
}
