<?php

declare(strict_types=1);

/**
 * Produces fresh `Validator` instances backed by a fresh `ErrorBag`.
 *
 * Each call to `create()` returns an independent validator carrying its own
 * empty bag. The bag's translator (if any) is supplied by the injected
 * `ErrorBagFactory`, so multiple `validate()` calls on the same validator
 * accumulate into the same translator-aware bag.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator;

use PHPdot\Container\Attribute\Scoped;
use PHPdot\Error\ErrorBagFactory;

#[Scoped]
final class ValidatorFactory
{
    /**
     * __construct.
     *
     * @param ErrorBagFactory $bags
     */
    public function __construct(
        private readonly ErrorBagFactory $bags = new ErrorBagFactory(),
    ) {}

    /**
     * Build a fresh `Validator` with its own empty bag.
     *
     * @return Validator
     */
    public function create(): Validator
    {
        return new Validator($this->bags->create());
    }
}
