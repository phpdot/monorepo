<?php

declare(strict_types=1);

/**
 * Provides additional context for the debug page.
 *
 * Each provider creates a "tab" in the debug UI (e.g. "Queries", "Route", "Cache").
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\ErrorHandler\Contract;

use Psr\Http\Message\ServerRequestInterface;

interface ContextProviderInterface
{
    /**
     * Tab label shown in the debug page.
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * Collect context data for the given exception.
     *
     * @param \Throwable $exception
     * @param ?ServerRequestInterface $request
     *
     * @return array<string, mixed> Key-value pairs shown in the tab
     */
    public function collect(\Throwable $exception, ?ServerRequestInterface $request): array;
}
