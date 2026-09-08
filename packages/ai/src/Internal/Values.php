<?php

declare(strict_types=1);

/**
 * The coercions the wire layer reads untrusted values through — the layer's own
 * `To`, internalized.
 *
 * A provider's frame is a document this package did not write: a number as a
 * string, a null where an array was expected, a scalar where a list was
 * declared. Nothing here trusts a type; every read coerces and every read has
 * a default. The signatures match the application helper this layer grew up
 * with, so the drivers lifted from it read unchanged.
 *
 * @internal Nothing outside this package calls it; it is not API.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Internal;

use Stringable;

final class Values
{
    /**
     * Cast value to string.
     *
     * An empty RESULT answers the default, not only an empty INPUT: the
     * registry reads config with `Values::string($settings['x'] ?? '', $fallback)`,
     * and a helper that returned '' for '' silently replaced every fallback
     * with an empty string — measured as a request key of `""` where
     * `max_completion_tokens` belonged, which the provider answers with
     * `Invalid property name in ''`.
     *
     * @param mixed $value Input value
     * @param string $default Default when the value coerces to nothing
     * @param bool $trim Trim whitespace
     *
     * @return string
     */
    public static function string(mixed $value, string $default = '', bool $trim = true): string
    {
        if ($value === null) {
            return $default;
        }

        $result = '';

        if (is_string($value)) {
            $result = $value;
        } elseif (is_int($value) || is_float($value)) {
            $result = (string) $value;
        } elseif (is_bool($value)) {
            $result = $value ? 'true' : 'false';
        } elseif ($value instanceof Stringable) {
            $result = $value->__toString();
        } elseif (is_array($value) || is_object($value)) {
            $json = json_encode($value);

            $result = $json !== false ? $json : '';
        }

        $result = $trim ? trim($result) : $result;

        return $result === '' ? $default : $result;
    }

    /**
     * Cast value to integer.
     *
     * @param mixed $value Input value
     * @param int|null $default Default if conversion fails; null keeps "absent" distinct from zero
     *
     * @return ($default is null ? int|null : int)
     */
    public static function int(mixed $value, null|int $default = 0): null|int
    {
        if ($value === null) {
            return $default;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return $default;
            }

            if (is_numeric($value)) {
                $intVal = (int) $value;
                $floatVal = (float) $value;

                if ((float) $intVal === $floatVal) {
                    return $intVal;
                }
            }
        }

        return $default;
    }

    /**
     * Cast value to array.
     *
     * @param mixed $value Input value
     * @param array<mixed> $default Default if conversion fails
     *
     * @return array<mixed>
     */
    public static function array(mixed $value, array $default = []): array
    {
        if ($value === null) {
            return $default;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return $default;
            }

            if (json_validate($value)) {
                $decoded = json_decode($value, true);

                return is_array($decoded) ? $decoded : $default;
            }
        }

        return $default;
    }
}
