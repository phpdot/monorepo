<?php

declare(strict_types=1);

/**
 * One tool call a model asked for, in this platform's shape.
 *
 * A CARRIER. The `id` is the PROVIDER'S — OpenAI calls it `tool_calls[].id`, Anthropic
 * calls it the `tool_use` block's `id` — and it is kept verbatim because the result
 * must be handed back under the same id or the provider cannot match them up. It is
 * opaque here and nothing reads it.
 *
 * `arguments` is DECODED. Both wire shapes stream it as partial JSON text that only
 * becomes an object once the last fragment lands; the drivers do that assembly, so
 * everything above them sees an array.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Conversation;

final readonly class ToolCallDTO
{
    /**
     * @param string $id The provider's own id for this call, handed back with the result
     * @param string $name Which tool
     * @param array<string, mixed> $arguments What it was called with, already decoded
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments = [],
    ) {}
}
