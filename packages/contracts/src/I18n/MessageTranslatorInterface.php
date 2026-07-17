<?php

declare(strict_types=1);

/**
 * Message translator contract — translates a key + ICU params into a localized string.
 *
 * Implementations are runtime-agnostic; consumers depend on this interface, not on
 * any concrete translator. Allows phpdot/error and any other package to consume a
 * translator without coupling to a specific implementation.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Contracts\I18n;

interface MessageTranslatorInterface
{
    /**
     * Translate a message key with ICU MessageFormat parameters.
     *
     * @param string $key The translation key (e.g. 'errors.user.not_found')
     * @param array<string, mixed> $params ICU interpolation parameters
     *
     * @return string
     */
    public function translate(string $key, array $params = []): string;
}
