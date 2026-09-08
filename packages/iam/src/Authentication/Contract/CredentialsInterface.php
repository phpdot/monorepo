<?php

declare(strict_types=1);

/**
 * Marker for the input a stage consumes. Each stage brings its own credential
 * type (a password now; a TOTP code later) and matches it via supports().
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication\Contract;

interface CredentialsInterface {}
