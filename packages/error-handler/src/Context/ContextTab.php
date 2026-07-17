<?php

declare(strict_types=1);

/**
 * A named tab of context data for the debug page.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\ErrorHandler\Context;

final readonly class ContextTab
{
    /**
     * A named tab of context data for the debug page.
     *
     * @param string $label Tab label (e.g. "Queries", "Route")
     * @param array<string, mixed> $data Key-value pairs shown in the tab
     */
    public function __construct(
        public string $label,
        public array $data,
    ) {}
}
