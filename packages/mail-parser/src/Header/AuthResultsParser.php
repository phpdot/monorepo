<?php

declare(strict_types=1);

/**
 * Parses Authentication-Results headers (RFC 8601) into structured method results for DKIM,
 * SPF, DMARC, and other authentication checks.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Header;

use PHPdot\Mail\Parser\Result\AuthResult;

final class AuthResultsParser
{
    public function parse(string $value): AuthResult
    {
        $raw = $value;
        $value = trim($value);

        $firstSemicolon = strpos($value, ';');
        $server = $firstSemicolon !== false
            ? trim(substr($value, 0, $firstSemicolon))
            : trim($value);
        $rest = $firstSemicolon !== false
            ? substr($value, $firstSemicolon + 1)
            : '';

        $dkim = null;
        $dkimDomain = null;
        $spf = null;
        $spfDomain = null;
        $dmarc = null;
        $dmarcDomain = null;
        $iprev = null;

        $methods = array_filter(array_map('trim', explode(';', $rest)), static fn(string $value): bool => $value !== '');

        foreach ($methods as $method) {
            $clean = preg_replace('/\([^)]*\)/', '', $method) ?? $method;
            $clean = trim($clean);

            if ($clean === '') {
                continue;
            }

            if (preg_match('/^([\w-]+)\s*=\s*(\S+)/i', $clean, $m) !== 1) {
                continue;
            }

            $methodName = strtolower($m[1]);
            $methodResult = strtolower($m[2]);

            $props = [];
            preg_match_all('/([\w.]+)=(\S+)/', $clean, $propMatches, PREG_SET_ORDER);
            foreach ($propMatches as $pm) {
                $key = strtolower($pm[1]);

                if ($key === $methodName) {
                    continue;
                }
                $props[$key] = $pm[2];
            }

            match ($methodName) {
                'dkim' => (function () use (&$dkim, &$dkimDomain, $methodResult, $props) {
                    $dkim = $methodResult;
                    $dkimDomain = $props['header.d'] ?? $props['header.i'] ?? null;
                })(),
                'spf' => (function () use (&$spf, &$spfDomain, $methodResult, $props) {
                    $spf = $methodResult;
                    $spfDomain = $props['smtp.mailfrom'] ?? $props['smtp.helo'] ?? null;
                })(),
                'dmarc' => (function () use (&$dmarc, &$dmarcDomain, $methodResult, $props) {
                    $dmarc = $methodResult;
                    $dmarcDomain = $props['header.from'] ?? null;
                })(),
                'iprev' => (function () use (&$iprev, $methodResult) {
                    $iprev = $methodResult;
                })(),
                default => null,
            };
        }

        return new AuthResult(
            server: $server !== '' ? $server : null,
            dkim: $dkim,
            dkimDomain: $dkimDomain,
            spf: $spf,
            spfDomain: $spfDomain,
            dmarc: $dmarc,
            dmarcDomain: $dmarcDomain,
            iprev: $iprev,
            raw: $raw,
        );
    }
}
