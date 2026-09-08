<?php

declare(strict_types=1);

/**
 * A CLI argument whose shape is wrong — not merely unknown — rejected
 * before any storage is touched.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Exception;

final class InvalidCliArgumentException extends IamException {}
