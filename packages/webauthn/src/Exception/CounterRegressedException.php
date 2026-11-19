<?php

declare(strict_types=1);

/**
 * An authentication presented a signature counter not above the stored one, while one of them was non-zero:
 * the sign of a cloned authenticator. Told apart from every other ceremony failure so a host can record it.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\WebAuthn\Exception;

final class CounterRegressedException extends WebauthnException {}
