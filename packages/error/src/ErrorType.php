<?php

declare(strict_types=1);

/**
 * Error category. Maps to a broad class of problems.
 *
 * The frontend uses this to decide presentation (red badge, yellow warning, etc.).
 * The error code gives the specific problem. The type gives the category.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Error;

enum ErrorType: string
{
    case VALIDATION     = 'validation';
    case AUTHENTICATION = 'authentication';
    case AUTHORIZATION  = 'authorization';
    case NOT_FOUND      = 'not_found';
    case CONFLICT       = 'conflict';
    case RATE_LIMIT     = 'rate_limit';
    case TIMEOUT        = 'timeout';
    case UNAVAILABLE    = 'unavailable';
    case SERVER         = 'server';
}
