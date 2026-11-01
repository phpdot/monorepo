<?php

declare(strict_types=1);

/**
 * Parses DKIM-Signature headers (RFC 6376) into structured tag-value pairs for domain,
 * selector, algorithm, and signature data.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Header;

use PHPdot\Mail\Parser\Result\DkimSignature;

final class DkimParser
{
    public function parse(string $value): DkimSignature
    {
        $tags = [];

        foreach (explode(';', $value) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $eqPos = strpos($part, '=');
            if ($eqPos === false) {
                continue;
            }

            $tag = trim(substr($part, 0, $eqPos));
            $val = trim(substr($part, $eqPos + 1));
            $tags[$tag] = $val;
        }

        if (isset($tags['b'])) {
            $tags['b'] = preg_replace('/\s+/', '', $tags['b']);
        }
        if (isset($tags['bh'])) {
            $tags['bh'] = preg_replace('/\s+/', '', $tags['bh']);
        }

        return new DkimSignature(
            version: $tags['v'] ?? null,
            algorithm: $tags['a'] ?? null,
            domain: $tags['d'] ?? null,
            selector: $tags['s'] ?? null,
            signature: $tags['b'] ?? null,
            bodyHash: $tags['bh'] ?? null,
            headers: $tags['h'] ?? null,
            canonicalization: $tags['c'] ?? null,
            identity: $tags['i'] ?? null,
            bodyLength: isset($tags['l']) ? (int) $tags['l'] : null,
            queryMethod: $tags['q'] ?? null,
            timestamp: isset($tags['t']) ? (int) $tags['t'] : null,
            expiration: isset($tags['x']) ? (int) $tags['x'] : null,
            raw: $value,
        );
    }
}
