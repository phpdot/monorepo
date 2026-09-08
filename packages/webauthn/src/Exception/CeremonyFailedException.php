<?php

declare(strict_types=1);

/**
 * A WebAuthn ceremony rejected the browser's response — challenge mismatch,
 * wrong origin, bad signature, counter regression, unsupported algorithm,
 * attestation refusal — or the challenge state is unknown, consumed, or
 * expired. Fail-closed: nothing about the failure state survives.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\WebAuthn\Exception;

final class CeremonyFailedException extends WebauthnException {}
