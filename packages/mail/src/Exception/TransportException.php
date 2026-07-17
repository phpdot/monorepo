<?php

declare(strict_types=1);

/**
 * Thrown when the underlying transport fails to deliver a message (connection
 * refused, authentication rejected, recipient declined, etc.).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Exception;

final class TransportException extends MailException {}
