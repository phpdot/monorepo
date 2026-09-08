<?php

declare(strict_types=1);

/**
 * What a driver can say while a turn is running.
 *
 * These are OURS. No vendor's event names reach past the driver, which is what lets the
 * host read one stream vocabulary regardless of who answered.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Event;

enum LlmEventType: string
{
    /** A fragment of the answer's text. */
    case Delta = 'delta';

    /** The model asked for a tool. Complete — the arguments are assembled and decoded. */
    case ToolCall = 'tool_call';

    /** The turn finished; usage is final. */
    case Done = 'done';

    /** The turn failed; the answer so far is whatever arrived before this. */
    case Error = 'error';
}
