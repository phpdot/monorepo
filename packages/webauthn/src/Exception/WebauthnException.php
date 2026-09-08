<?php

declare(strict_types=1);

/**
 * The open base of every exception the package throws, so a host can catch
 * the whole domain in one clause. Leaves are final; every vendor failure is
 * translated into this tree at the ceremony fence — no web-auth, CBOR, cose,
 * or Symfony type ever leaks past the boundary.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\WebAuthn\Exception;

use RuntimeException;
use Throwable;
use Webauthn\Exception\WebauthnException as VendorWebauthnException;

class WebauthnException extends RuntimeException
{
    /**
     * Translate any ceremony failure — vendor tree, metadata tree, or a
     * foreign CBOR/cose/SPL escape — into the package hierarchy.
     *
     * @param Throwable $failure The caught failure
     *
     * @return self
     */
    public static function from(Throwable $failure): self
    {
        if ($failure instanceof VendorWebauthnException) {
            return new CeremonyFailedException($failure->getMessage(), 0, $failure);
        }

        return new CeremonyFailedException(
            sprintf('The ceremony failed: %s', $failure->getMessage()),
            0,
            $failure,
        );
    }
}
