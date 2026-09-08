<?php

declare(strict_types=1);

/**
 * A requirement or pending-auth shape the engine refuses to carry: an empty
 * factor, a malformed stage list, or an index no requirement lives at.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Exception;

final class InvalidRequirementException extends IamException {}
