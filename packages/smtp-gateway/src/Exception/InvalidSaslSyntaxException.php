<?php

declare(strict_types=1);

/**
 * SASL syntax error — the client's base64 could not be decoded.
 *
 * Distinct from an authentication failure: RFC 4954 §4 requires a 501
 * (syntax error in parameters) with enhanced code 5.5.2 for undecodable
 * base64, while a rejected credential is 535.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Exception;

final class InvalidSaslSyntaxException extends AuthenticationException {}
