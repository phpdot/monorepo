<?php

declare(strict_types=1);

/**
 * Safe reads into a decoded JSON document.
 *
 * `json_decode` answers `mixed`, and a provider's frame is a document this
 * platform did not write — every level of it may be absent, or a string where
 * an array was expected. Walking it with `$frame['choices'][0]['delta']` is
 * three unchecked assumptions on one line, and the failure is a fatal in the
 * middle of a stream that has already been half-delivered.
 *
 * So the walk is done once, here, checking at each step. Callers put a `Values::`
 * coercion around the result and get a typed value or a default.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Transport;

final class Json
{
    /**
     * One decoded frame, or null when it was not a JSON object at all.
     *
     * @param string $raw The frame's data
     *
     * @return array<mixed>|null
     */
    public static function decode(string $raw): null|array
    {
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * The value at a path, or null when any step of it is missing.
     *
     * @param mixed $data Whatever was decoded
     * @param list<string|int> $path The keys to walk, outermost first
     *
     * @return mixed
     */
    public static function get(mixed $data, array $path): mixed
    {
        foreach ($path as $key) {
            if (!is_array($data) || !array_key_exists($key, $data)) {
                return null;
            }

            $data = $data[$key];
        }

        return $data;
    }
}
