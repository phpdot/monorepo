<?php

declare(strict_types=1);

/**
 * The thread a driver is asked to continue.
 *
 * A CARRIER. The window is already applied and the system prompt already decided by
 * the time this exists — the application does both, because a DTO that trims its own
 * history is a DTO that decides something.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Conversation;

final readonly class ConversationDTO
{
    /**
     * @param list<MessageDTO> $messages The turns to replay, oldest first, System excluded
     * @param ?string $system The system instruction, or null for none
     */
    public function __construct(
        public array $messages,
        public null|string $system = null,
    ) {}
}
