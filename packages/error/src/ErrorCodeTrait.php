<?php

declare(strict_types=1);

/**
 * Default implementation of ErrorCodeInterface for backed string enums.
 *
 * The enum's string value becomes the error code.
 * The enum must implement getDetails() returning the error metadata.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Error;

trait ErrorCodeTrait
{
    public function getCode(): string
    {
        return $this->value;
    }

    public function getMessage(): string
    {
        return $this->getDetails()['message'];
    }

    public function getDescription(): string
    {
        return $this->getDetails()['description'];
    }

    public function getType(): ErrorType
    {
        return $this->getDetails()['type'];
    }

    public function getHttpStatus(): int
    {
        return $this->getDetails()['httpStatus'];
    }
}
