<?php

declare(strict_types=1);

namespace PHPdot\Error\Tests\Fixtures;

use PHPdot\Error\ErrorCodeInterface;
use PHPdot\Error\ErrorCodeTrait;
use PHPdot\Error\ErrorType;
use PHPdot\Error\HttpStatus;

enum UserErrors: string implements ErrorCodeInterface
{
    use ErrorCodeTrait;

    case NOT_FOUND     = '00010001';
    case EMAIL_TAKEN   = '00010002';
    case INVALID_EMAIL = '00010003';
    case WEAK_PASSWORD = '00010004';
    case LOCKED        = '00010005';

    /**
     * @return array{message: string, description: string, type: ErrorType, httpStatus: int}
     */
    public function getDetails(): array
    {
        return match ($this) {
            self::NOT_FOUND => [
                'message' => 'User not found',
                'description' => 'errors.user.not_found',
                'type' => ErrorType::NOT_FOUND,
                'httpStatus' => HttpStatus::NOT_FOUND->value,
            ],
            self::EMAIL_TAKEN => [
                'message' => 'Email is already taken',
                'description' => 'errors.user.email_taken',
                'type' => ErrorType::CONFLICT,
                'httpStatus' => HttpStatus::CONFLICT->value,
            ],
            self::INVALID_EMAIL => [
                'message' => 'Invalid email address',
                'description' => 'errors.user.invalid_email',
                'type' => ErrorType::VALIDATION,
                'httpStatus' => HttpStatus::UNPROCESSABLE_ENTITY->value,
            ],
            self::WEAK_PASSWORD => [
                'message' => 'Password must be at least 8 characters',
                'description' => 'errors.user.weak_password',
                'type' => ErrorType::VALIDATION,
                'httpStatus' => HttpStatus::UNPROCESSABLE_ENTITY->value,
            ],
            self::LOCKED => [
                'message' => 'Account is locked',
                'description' => 'errors.user.account_locked',
                'type' => ErrorType::AUTHORIZATION,
                'httpStatus' => HttpStatus::FORBIDDEN->value,
            ],
        };
    }
}
