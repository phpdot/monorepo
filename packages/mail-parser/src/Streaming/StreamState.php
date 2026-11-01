<?php

declare(strict_types=1);

/**
 * State machine states for the line-by-line streaming email parser.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Streaming;

enum StreamState
{
    case Header;
    case Preamble;
    case Body;
    case Epilogue;
    case Finished;
}
