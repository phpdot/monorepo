<?php

declare(strict_types=1);

/**
 * Base for every exception thrown by the package — catch this to trap any mail
 * failure.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Exception;

class MailException extends \RuntimeException {}
