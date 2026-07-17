<?php

declare(strict_types=1);

/**
 * Resolver
 *
 * Resolves variable interpolation in parsed entries with circular reference detection.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Env\Parser;

use PHPdot\Env\Exception\ParseException;

final class Resolver
{
    /**
     * Resolve variable references in entries.
     *
     * @param list<Entry> $entries Entries from the Lexer.
     * @param array<string, string> $predefined Pre-existing values from earlier files.
     *
     * @throws ParseException On circular references.
     *
     * @return list<Entry> Entries with resolved values.
     */
    public function resolve(array $entries, array $predefined = []): array
    {
        $map = $predefined;

        foreach ($entries as $entry) {
            $map[$entry->key] = $entry->value;
        }

        $resolved = [];

        foreach ($entries as $entry) {
            if (!$entry->interpolate) {
                $resolved[] = $entry;
                continue;
            }

            $value = $this->resolveValue($entry->value, $map, [$entry->key], $entry->line);
            $map[$entry->key] = $value;
            $resolved[] = new Entry($entry->key, $value, $entry->line, $entry->interpolate);
        }

        return $resolved;
    }

    /**
     * Resolve variable references within a single value string.
     *
     * @param string $value The value containing potential variable references.
     * @param array<string, string> $map The variable map for lookups.
     * @param list<string> $chain The resolution chain for circular detection.
     * @param int $line The line number for error reporting.
     *
     * @throws ParseException On circular references.
     *
     * @return string The value with all references resolved.
     */
    private function resolveValue(string $value, array &$map, array $chain, int $line): string
    {
        $value = preg_replace_callback(
            '/\$\{([A-Za-z_][A-Za-z0-9_]*)\}/',
            function (array $matches) use (&$map, $chain, $line): string {
                return $this->resolveReference($matches[1], $map, $chain, $line);
            },
            $value,
        );

        if (!is_string($value)) {
            return '';
        }

        $value = preg_replace_callback(
            '/\$([A-Za-z_][A-Za-z0-9_]*)/',
            function (array $matches) use (&$map, $chain, $line): string {
                return $this->resolveReference($matches[1], $map, $chain, $line);
            },
            $value,
        );

        if (!is_string($value)) {
            return '';
        }

        return $value;
    }

    /**
     * Resolve a single variable reference by name.
     *
     * @param string $name The variable name to resolve.
     * @param array<string, string> $map The variable map for lookups.
     * @param list<string> $chain The resolution chain for circular detection.
     * @param int $line The line number for error reporting.
     *
     * @throws ParseException On circular references.
     *
     * @return string The resolved value.
     */
    private function resolveReference(string $name, array &$map, array $chain, int $line): string
    {
        if (in_array($name, $chain, true)) {
            throw new ParseException(
                'Circular reference detected: ' . implode(' -> ', [...$chain, $name]),
                $line,
            );
        }

        if (!array_key_exists($name, $map)) {
            return '';
        }

        $resolved = $this->resolveValue($map[$name], $map, [...$chain, $name], $line);
        $map[$name] = $resolved;

        return $resolved;
    }
}
