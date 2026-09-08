<?php

declare(strict_types=1);

/**
 * One provider, reduced to the two questions the application asks it.
 *
 * The port that makes this platform provider-agnostic. Everything a vendor calls its
 * own — event names, message shapes, token fields, error envelopes, and above all how
 * a tool call is spelled — stops at the implementation of this interface.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Contract;

use Closure;
use PHPdot\Ai\Conversation\ConversationDTO;
use PHPdot\Ai\Event\LlmEvent;
use PHPdot\Ai\Exception\LlmError;
use PHPdot\Ai\Registry\LlmShape;

interface LlmDriver
{
    /**
     * Which wire shape this driver speaks.
     *
     * @return LlmShape
     */
    public function shape(): LlmShape;

    /**
     * What this driver can do.
     *
     * @return LlmCapabilities
     */
    public function capabilities(): LlmCapabilities;

    /**
     * Run one turn, reporting as it goes.
     *
     * The callback returns false to abandon the turn — a reader who closed the page,
     * typically. A driver that ignores it keeps a worker and a provider connection
     * alive for an answer nobody will read.
     *
     * @param ConversationDTO $conversation The thread to continue
     * @param string $model The provider's own model identifier
     * @param list<ToolDefinition> $tools What the model may call, or empty for none
     * @param Closure(LlmEvent): bool $onEvent Consumes each event; false abandons the turn
     *
     * @throws LlmError If the provider did not answer, or refused
     */
    public function stream(ConversationDTO $conversation, string $model, array $tools, Closure $onEvent): void;
}
