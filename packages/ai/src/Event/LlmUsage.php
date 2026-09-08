<?php

declare(strict_types=1);

/**
 * What a turn cost, in the only unit every provider agrees on.
 *
 * Zero means NOT REPORTED, not free — some compatible endpoints omit usage
 * entirely, and inventing a count from the text would be a number that reads
 * exactly like a measured one.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Event;

final readonly class LlmUsage
{
    /**
     * @param int $inputTokens Tokens the provider charged for the prompt
     * @param int $outputTokens Tokens the provider charged for the answer
     */
    public function __construct(
        public int $inputTokens = 0,
        public int $outputTokens = 0,
    ) {}
}
