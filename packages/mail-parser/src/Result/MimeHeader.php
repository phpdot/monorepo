<?php

declare(strict_types=1);

/**
 * Value object representing a parsed MIME header with primary value and case-insensitive
 * parameter lookup.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Result;

final readonly class MimeHeader
{
    /** @param array<string, string> $params */
    public function __construct(
        public string $value,
        public array $params = [],
    ) {}

    public function getParam(string $name): string|null
    {
        $lower = strtolower($name);

        foreach ($this->params as $key => $value) {
            if (strtolower($key) === $lower) {
                return $value;
            }
        }

        return null;
    }
}
