<?php

declare(strict_types=1);

/**
 * A SessionConfig value is outside its valid domain.
 *
 * Raised at construction — bad sameSite values, non-positive lifetimes,
 * SameSite=None without Secure — inside the package hierarchy, so
 * `catch (SessionException)` covers configuration errors alongside runtime
 * session failures.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Session\Exception;

final class SessionConfigException extends SessionException {}
