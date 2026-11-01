<?php

declare(strict_types=1);

/**
 * Parses Received-SPF headers (RFC 7208) into structured results including pass/fail status,
 * client IP, and domain information.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Header;

use PHPdot\Mail\Parser\Result\SpfResult;

final class SpfParser
{
    public function parse(string $value): SpfResult
    {
        $raw = $value;
        $value = trim($value);

        preg_match('/^(\S+)/', $value, $m);
        $result = strtolower($m[1] ?? 'none');

        $remaining = preg_replace('/\([^)]*\)/', '', $value) ?? $value;

        $kvPairs = [];
        preg_match_all('/([\w][\w-]*)=([^;\s]+)/', $remaining, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $kvPairs[strtolower($match[1])] = $match[2];
        }

        $envelopeFrom = $kvPairs['envelope-from'] ?? null;
        $domain = $envelopeFrom;
        $at = $envelopeFrom === null ? false : strpos($envelopeFrom, '@');
        if ($at !== false) {
            $domain = substr($envelopeFrom, $at + 1);
        }

        $domain ??= $kvPairs['helo'] ?? null;

        return new SpfResult(
            result: $result,
            domain: $domain,
            ip: $kvPairs['client-ip'] ?? null,
            mechanism: $kvPairs['mechanism'] ?? null,
            receiver: $kvPairs['receiver'] ?? null,
            raw: $raw,
        );
    }
}
