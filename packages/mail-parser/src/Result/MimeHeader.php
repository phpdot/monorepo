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

    /**
     * Whether the value is a `type/subtype` as RFC 2045 §5.1 spells one; a part whose Content-Type is not is read as
     * text/plain (§5.2).
     */
    public function isMediaType(): bool
    {
        return preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+\/[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/', $this->value) === 1;
    }

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
