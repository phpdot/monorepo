<?php

declare(strict_types=1);

/**
 * A delivery the receiving side refused for good, which no retry would change.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Exception;

final class PermanentDeliveryException extends DeliveryException {}
