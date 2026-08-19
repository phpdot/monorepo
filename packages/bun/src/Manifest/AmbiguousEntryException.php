<?php

declare(strict_types=1);

/**
 * Thrown when a lookup names no entrypoint and the manifest cannot supply one unambiguously.
 *
 * Omitting the entry is only meaningful when the build produced exactly one, which is the common
 * case: a single application bundle. With several, guessing would silently serve the wrong file, so
 * the caller is told to name one — and which ones exist. With none, the build produced nothing to
 * serve, which is a build failure surfacing at the first request instead of a blank page.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Manifest;

use PHPdot\Bun\Exception\BunException;

final class AmbiguousEntryException extends \RuntimeException implements BunException
{
    /**
     * @param string $message
     */
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    /**
     * The manifest holds no entrypoints at all.
     *
     * @param string $manifestPath
     *
     * @return self
     */
    public static function none(string $manifestPath): self
    {
        return new self(sprintf(
            'The manifest at "%s" lists no entrypoints, so there is nothing to resolve. '
            . 'Run the asset build, or check that the build wrote its manifest.',
            $manifestPath,
        ));
    }

    /**
     * The manifest holds more than one entrypoint, so the caller must choose.
     *
     * @param list<string> $available
     *
     * @return self
     */
    public static function several(array $available): self
    {
        return new self(sprintf(
            'The manifest lists %d entrypoints, so a lookup must name the one it wants: %s',
            count($available),
            implode(', ', $available),
        ));
    }
}
