<?php

declare(strict_types=1);

/**
 * Who said a thing.
 *
 * `Tool` is not a role either wire shape actually has — OpenAI spells a result as a
 * `tool` message, Anthropic as a `tool_result` block inside a USER message. Neither
 * spelling is canonical here; the drivers translate. A fourth role is what lets one
 * stored transcript replay to either.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Conversation;

enum MessageRole: string
{
    case System = 'system';
    case User = 'user';
    case Assistant = 'assistant';
    case Tool = 'tool';
}
