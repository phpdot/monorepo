<?php

declare(strict_types=1);

/**
 * One thing that happened during a turn, in this platform's own vocabulary.
 *
 * A CARRIER. Drivers build these; nothing else does.
 *
 * A ToolCall event is only emitted once its arguments are COMPLETE. Both wire shapes
 * stream tool arguments as partial JSON that is invalid at every point but the last, so
 * a driver that forwarded fragments would be handing the loop text it cannot decode and
 * cannot know is unfinished.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Event;

use PHPdot\Ai\Conversation\ToolCallDTO;

final readonly class LlmEvent
{
    /**
     * @param LlmEventType $type What kind of event this is
     * @param string $text The text fragment, for a Delta
     * @param ?ToolCallDTO $toolCall The assembled call, for a ToolCall
     * @param ?LlmUsage $usage What the turn cost, for a Done
     * @param ?string $message Why it failed, for an Error
     */
    private function __construct(
        public LlmEventType $type,
        public string $text = '',
        public null|ToolCallDTO $toolCall = null,
        public null|LlmUsage $usage = null,
        public null|string $message = null,
    ) {}

    /**
     * A fragment of the answer.
     *
     * @param string $text The fragment
     *
     * @return self
     */
    public static function delta(string $text): self
    {
        return new self(LlmEventType::Delta, text: $text);
    }

    /**
     * The model asked for a tool, and the arguments are complete.
     *
     * @param ToolCallDTO $call What it asked for
     *
     * @return self
     */
    public static function toolCall(ToolCallDTO $call): self
    {
        return new self(LlmEventType::ToolCall, toolCall: $call);
    }

    /**
     * The turn finished.
     *
     * @param LlmUsage $usage What it cost
     *
     * @return self
     */
    public static function done(LlmUsage $usage): self
    {
        return new self(LlmEventType::Done, usage: $usage);
    }

    /**
     * The turn failed.
     *
     * @param string $message Why
     *
     * @return self
     */
    public static function error(string $message): self
    {
        return new self(LlmEventType::Error, message: $message);
    }
}
