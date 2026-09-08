<?php

declare(strict_types=1);

/**
 * The arguments one tool call arrived with, read the way a request body is read.
 *
 * A model's arguments are as untrusted as a form post and arrive shaped worse: a
 * number as a string, a single value where a list was declared, a key absent
 * entirely because the model judged it irrelevant. So nothing here trusts a type —
 * every read coerces and every read has a default. The coercion lives here and
 * nowhere else: a tool method receives one of these and never re-checks a scalar.
 *
 * `list()` accepts a bare scalar and wraps it. That is not leniency for its own
 * sake: `channels: "sms"` instead of `channels: ["sms"]` is the single most common
 * shape a model gets wrong, and refusing it costs a round trip to be told something
 * the value already made obvious.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mcp\Tool;

use Stringable;

final readonly class ToolArguments
{
    /**
     * @param array<string, mixed> $arguments What the model sent
     */
    public function __construct(private array $arguments = []) {}

    /**
     * A string argument.
     *
     * @param string $key Which argument
     * @param string $default What to use when it is absent or blank
     *
     * @return string
     */
    public function string(string $key, string $default = ''): string
    {
        $value = $this->text($this->arguments[$key] ?? '');

        return $value === '' ? $default : $value;
    }

    /**
     * An integer argument, clamped to a range.
     *
     * @param string $key Which argument
     * @param int $default What to use when it is absent
     * @param int $min The floor
     * @param int $max The ceiling
     *
     * @return int
     */
    public function int(string $key, int $default, int $min, int $max): int
    {
        $value = $this->whole($this->arguments[$key] ?? null, $default) ?? $default;

        return max($min, min($max, $value));
    }

    /**
     * A list of strings, accepting a bare scalar as a list of one.
     *
     * @param string $key Which argument
     *
     * @return list<string>
     */
    public function list(string $key): array
    {
        $raw = $this->arguments[$key] ?? null;

        if ($raw === null || $raw === '') {
            return [];
        }

        $values = [];

        foreach (is_array($raw) ? $raw : [$raw] as $item) {
            $value = $this->text($item);

            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * A list of integers, accepting a bare scalar as a list of one.
     *
     * @param string $key Which argument
     *
     * @return list<int>
     */
    public function integers(string $key): array
    {
        $numbers = [];

        foreach ($this->list($key) as $value) {
            $number = $this->whole($value, null);

            if ($number !== null) {
                $numbers[] = $number;
            }
        }

        return $numbers;
    }

    /**
     * A THREE-STATE flag: true, false, or absent.
     *
     * Absent is a real answer and not a default. `enabled` on a search means "only
     * enabled", "only disabled", or "do not care" — and a tool that collapsed the third
     * into `false` would quietly answer a narrower question than the model asked, which
     * is worse than refusing because the answer looks right.
     *
     * @param string $key Which argument
     *
     * @return bool|null Null when the argument was not sent
     */
    public function flag(string $key): bool|null
    {
        if (!$this->has($key)) {
            return null;
        }

        return $this->boolean($this->arguments[$key] ?? null, null);
    }

    /**
     * Whether an argument was sent at all.
     *
     * @param string $key Which argument
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->arguments);
    }

    /**
     * Anything, as text.
     *
     * @param mixed $value What the model sent
     *
     * @return string
     */
    private function text(mixed $value): string
    {
        if ($value === null) {
            return '';
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

        return trim($result);
    }

    /**
     * Anything, as a whole number — or the default when the value holds none.
     *
     * @param mixed $value What the model sent
     * @param int|null $default What to use when the value is absent or not a number; null keeps "absent" distinct from zero
     *
     * @return int|null
     */
    private function whole(mixed $value, int|null $default): int|null
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
            $trimmed = trim($value);

            if ($trimmed === '') {
                return $default;
            }

            if (is_numeric($trimmed)) {
                $whole = (int) $trimmed;
                $exact = (float) $trimmed;

                if ((float) $whole === $exact) {
                    return $whole;
                }
            }
        }

        return $default;
    }

    /**
     * Anything, as a flag — or the default when the value holds no answer.
     *
     * Empty is absence, not falsity: a caller defaulting to false still gets false;
     * one defaulting to null gets "not asked".
     *
     * @param mixed $value What the model sent
     * @param bool|null $default What to use when the value holds no answer
     *
     * @return bool|null
     */
    private function boolean(mixed $value, bool|null $default): bool|null
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value !== 0.0;
        }

        if (is_string($value)) {
            $lower = strtolower(trim($value));

            if ($lower === '') {
                return $default;
            }

            if (in_array($lower, ['1', 'true', 'yes', 'y', 'on'], true)) {
                return true;
            }

            if (in_array($lower, ['0', 'false', 'no', 'n', 'off'], true)) {
                return false;
            }
        }

        return $default;
    }
}
