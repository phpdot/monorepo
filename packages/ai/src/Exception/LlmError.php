<?php

declare(strict_types=1);

/**
 * A turn could not run: the provider did not answer, refused, or the key was
 * never set.
 *
 * Thrown by drivers and the Drivers accessor only, always BEFORE or AT the
 * start of a stream — a failure that arrives mid-stream is an Error event
 * instead, so a turn that dies halfway still reaches the reader as a stream
 * and not as a dead connection they have to guess about.
 *
 * The message is prose and deliberately so: nothing in the estate switches on
 * why a turn failed today, and a category nobody reads is taxonomy for its own
 * sake. The day a retry policy exists is the day this grows one.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Exception;

final class LlmError extends AiException {}
