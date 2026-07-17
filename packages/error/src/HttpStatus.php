<?php

declare(strict_types=1);

/**
 * HTTP status codes as a typed enum.
 *
 * Used in ErrorCodeInterface implementations for IDE autocompletion
 * and compile-time safety. Stored as int in ErrorEntry.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Error;

enum HttpStatus: int
{
    case OK                    = 200;
    case CREATED               = 201;
    case ACCEPTED              = 202;
    case NO_CONTENT            = 204;

    case MOVED_PERMANENTLY     = 301;
    case FOUND                 = 302;
    case NOT_MODIFIED          = 304;
    case TEMPORARY_REDIRECT    = 307;
    case PERMANENT_REDIRECT    = 308;

    case BAD_REQUEST           = 400;
    case UNAUTHORIZED          = 401;
    case FORBIDDEN             = 403;
    case NOT_FOUND             = 404;
    case METHOD_NOT_ALLOWED    = 405;
    case CONFLICT              = 409;
    case GONE                  = 410;
    case PAYLOAD_TOO_LARGE     = 413;
    case UNSUPPORTED_MEDIA     = 415;
    case UNPROCESSABLE_ENTITY  = 422;
    case TOO_MANY_REQUESTS     = 429;

    case INTERNAL_SERVER_ERROR = 500;
    case BAD_GATEWAY           = 502;
    case SERVICE_UNAVAILABLE   = 503;
    case GATEWAY_TIMEOUT       = 504;
}
