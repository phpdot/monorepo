<?php

declare(strict_types=1);

/**
 * What a driver can actually do, so the application degrades against a fact
 * rather than against a lowest common denominator.
 *
 * Flattening every provider to what the weakest local model supports would give
 * up features permanently to accommodate something nobody runs. The app asks
 * instead.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Contract;

final readonly class LlmCapabilities
{
    /**
     * @param bool $streaming Can answer incrementally
     * @param bool $systemPrompt Accepts a system instruction
     * @param bool $usageReporting Reports token counts it charged for
     * @param bool $toolUse Can call tools — both shipped drivers do; a driver that cannot says false
     */
    public function __construct(
        public bool $streaming = true,
        public bool $systemPrompt = true,
        public bool $usageReporting = true,
        public bool $toolUse = false,
    ) {}
}
