<?php

declare(strict_types=1);

namespace PHPdot\Validator\Tests\Stubs;

use PHPdot\Error\ErrorCodeInterface;
use PHPdot\Error\ErrorType;

enum TestErrorCode: string implements ErrorCodeInterface
{
    case UsernameRequired = '99010001';
    case UsernameTooShort = '99010002';
    case UsernameTooLong  = '99010003';
    case EmailRequired    = '99010004';
    case EmailInvalid     = '99010005';
    case AgeOutOfRange    = '99010006';
    case StartDateInvalid = '99010007';
    case EndDateBeforeStart = '99010008';
    case DateRangeTooLong = '99010009';
    case PasswordWeak     = '99010010';
    case RoleInvalid      = '99010011';
    case PasswordMismatch = '99010012';
    case Generic          = '99010099';

    public function getMessage(): string
    {
        return match ($this) {
            self::UsernameRequired   => 'Username is required.',
            self::UsernameTooShort   => 'Username must be at least :min characters.',
            self::UsernameTooLong    => 'Username may not exceed :max characters.',
            self::EmailRequired      => 'Email is required.',
            self::EmailInvalid       => 'Please enter a valid email address.',
            self::AgeOutOfRange      => 'Age must be between :min and :max.',
            self::StartDateInvalid   => 'Start date is invalid.',
            self::EndDateBeforeStart => 'End date must be on or after the start date.',
            self::DateRangeTooLong   => 'Date range cannot exceed :max days.',
            self::PasswordWeak       => 'Password must include letters and numbers.',
            self::RoleInvalid        => 'The selected role is not allowed.',
            self::PasswordMismatch   => 'Password confirmation does not match.',
            self::Generic            => 'The :field field is invalid.',
        };
    }

    public function getDescription(): string
    {
        return 'errors.test.' . strtolower($this->name);
    }

    public function getType(): ErrorType
    {
        return ErrorType::VALIDATION;
    }

    public function getHttpStatus(): int
    {
        return 422;
    }

    public function getCode(): string
    {
        return $this->value;
    }

    /**
     * @return array{
     *     message: string,
     *     description: string,
     *     type: ErrorType,
     *     httpStatus: int,
     * }
     */
    public function getDetails(): array
    {
        return [
            'message' => $this->getMessage(),
            'description' => $this->getDescription(),
            'type' => $this->getType(),
            'httpStatus' => $this->getHttpStatus(),
        ];
    }
}
