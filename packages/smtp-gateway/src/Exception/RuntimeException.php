<?php

declare(strict_types=1);

/**
 * General runtime exception for SMTP operations.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Exception;

class RuntimeException extends \RuntimeException implements GatewayException {}
