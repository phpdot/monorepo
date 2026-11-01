<?php

declare(strict_types=1);

/**
 * Parses Received email headers (RFC 5321) into structured hop information including hostnames,
 * IP addresses, protocols, and timestamps.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Header;

use PHPdot\Mail\Parser\Result\ReceivedHop;

final class ReceivedHeaderParser
{
    public function __construct(
        private readonly DateParser $dateParser,
    ) {}

    public function parse(string $value): ReceivedHop
    {
        $raw = $value;
        $date = null;
        $from = null;
        $fromIp = null;
        $by = null;
        $byIp = null;
        $via = null;
        $with = null;
        $id = null;
        $for = null;

        $lastSemicolon = strrpos($value, ';');
        if ($lastSemicolon !== false) {
            $dateStr = trim(substr($value, $lastSemicolon + 1));
            $value = substr($value, 0, $lastSemicolon);
            $date = $this->dateParser->parse($dateStr);
        }

        if (preg_match('/\bfrom\s+(.+?)(?=\s+(?:by|via|with|id|for)\b|$)/is', $value, $m) === 1) {
            $fromClause = trim($m[1]);
            $from = $this->extractHostname($fromClause);
            $fromIp = $this->extractIp($fromClause);
        }

        if (preg_match('/\bby\s+(.+?)(?=\s+(?:from|via|with|id|for)\b|$)/is', $value, $m) === 1) {
            $byClause = trim($m[1]);
            $by = $this->extractHostname($byClause);
            $byIp = $this->extractIp($byClause);
        }

        if (preg_match('/\bvia\s+(\S+)/i', $value, $m) === 1) {
            $via = trim($m[1]);
        }

        if (preg_match('/\bwith\s+(\S+)/i', $value, $m) === 1) {
            $with = trim($m[1]);
        }

        if (preg_match('/\bid\s+(\S+)/i', $value, $m) === 1) {
            $id = trim($m[1]);
        }

        if (preg_match('/\bfor\s+<?([^>;]+)>?/i', $value, $m) === 1) {
            $for = trim($m[1]);
        }

        return new ReceivedHop(
            from: $from,
            fromIp: $fromIp,
            by: $by,
            byIp: $byIp,
            via: $via,
            with: $with,
            id: $id,
            for: $for,
            date: $date,
            raw: $raw,
        );
    }

    private function extractHostname(string $clause): string|null
    {
        $clause = trim($clause);

        $cleaned = preg_replace('/\(.*?\)/s', '', $clause) ?? $clause;
        $cleaned = trim($cleaned);

        if ($cleaned === '') {
            return null;
        }

        $split = preg_split('/\s+/', $cleaned);
        $parts = $split === false ? [] : $split;

        if ($parts === [] || $parts[0] === '') {
            return null;
        }

        return $parts[0];
    }

    private function extractIp(string $clause): string|null
    {
        if (preg_match('/\[?(\d{1,3}(?:\.\d{1,3}){3})\]?/', $clause, $m) === 1) {
            return $m[1];
        }

        if (preg_match('/\[?IPv6:([^\]\)]+)\]?/i', $clause, $m) === 1) {
            return $m[1];
        }

        return null;
    }
}
