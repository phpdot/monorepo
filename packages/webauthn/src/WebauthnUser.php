<?php

declare(strict_types=1);

/**
 * The WebAuthn view of an account: the opaque handle the authenticator signs
 * (at most 64 raw bytes — the library refuses longer), the login name it
 * presents, and a display name. Built by the host's user repository; never
 * carries more than the ceremony needs.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\WebAuthn;

use PHPdot\WebAuthn\Exception\ConfigurationException;

final readonly class WebauthnUser
{
    public function __construct(
        public string $handle,
        public string $name,
        public string $displayName = '',
    ) {
        if ($handle === '' || strlen(Base64Url::decode($handle)) > 64) {
            throw ConfigurationException::missing('a user handle is required, at most 64 raw bytes');
        }
    }
}
