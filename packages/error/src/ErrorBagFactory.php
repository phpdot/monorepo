<?php

declare(strict_types=1);

/**
 * Produces fresh `ErrorBag` instances pre-wired with an optional translator.
 *
 * Each call to `create()` returns an independent bag. When the factory is
 * constructed with a `MessageTranslatorInterface`, every bag it produces
 * translates the entry's description key at `add()` time.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Error;

use PHPdot\Container\Attribute\Scoped;
use PHPdot\Contracts\I18n\MessageTranslatorInterface;

#[Scoped]
final class ErrorBagFactory
{
    /**
     * __construct.
     *
     * @param ?MessageTranslatorInterface $translator
     */
    public function __construct(
        private readonly ?MessageTranslatorInterface $translator = null,
    ) {}

    /**
     * Build a fresh `ErrorBag` carrying the configured translator (if any).
     *
     * @return ErrorBag
     */
    public function create(): ErrorBag
    {
        return new ErrorBag($this->translator);
    }
}
