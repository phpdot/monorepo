<?php

declare(strict_types=1);

/**
 * One message, in this platform's shape rather than any vendor's.
 *
 * A CARRIER. This is what the store holds and what every layer above the drivers sees.
 * Persisting a provider's own message array instead would weld the thread to that
 * provider and make changing model mid-conversation impossible, which is the entire
 * premise being defended — and it matters more now than it did before tools, because
 * the two shapes disagree about tool results at the level of ROLE, not just field
 * names.
 *
 * Three shapes travel in this one class:
 *
 *   - a plain turn — `role` and `content`
 *   - an assistant turn that asked for tools — `toolCalls`, with `content` often empty
 *   - a tool's answer — `role: Tool`, carrying the `toolCallId` it answers and the
 *     `toolName` that produced it
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Conversation;

final readonly class MessageDTO
{
    /**
     * @param MessageRole $role Who said it
     * @param string $content What was said, or a tool's answer as text
     * @param list<ToolCallDTO> $toolCalls The tools this assistant turn asked for
     * @param ?string $toolCallId The call this result answers, on a Tool turn
     * @param string $toolName Which tool produced it, on a Tool turn
     */
    public function __construct(
        public MessageRole $role,
        public string $content = '',
        public array $toolCalls = [],
        public null|string $toolCallId = null,
        public string $toolName = '',
    ) {}
}
