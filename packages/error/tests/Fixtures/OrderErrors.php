<?php

declare(strict_types=1);

namespace PHPdot\Error\Tests\Fixtures;

use PHPdot\Error\ErrorCodeInterface;
use PHPdot\Error\ErrorCodeTrait;
use PHPdot\Error\ErrorType;
use PHPdot\Error\HttpStatus;

enum OrderErrors: string implements ErrorCodeInterface
{
    use ErrorCodeTrait;

    case NOT_FOUND       = '00020001';
    case ALREADY_SHIPPED = '00020002';
    case PAYMENT_FAILED  = '00020003';

    /**
     * @return array{message: string, description: string, type: ErrorType, httpStatus: int}
     */
    public function getDetails(): array
    {
        return match ($this) {
            self::NOT_FOUND => [
                'message' => 'Order not found',
                'description' => 'errors.order.not_found',
                'type' => ErrorType::NOT_FOUND,
                'httpStatus' => HttpStatus::NOT_FOUND->value,
            ],
            self::ALREADY_SHIPPED => [
                'message' => 'Order has already been shipped',
                'description' => 'errors.order.already_shipped',
                'type' => ErrorType::CONFLICT,
                'httpStatus' => HttpStatus::CONFLICT->value,
            ],
            self::PAYMENT_FAILED => [
                'message' => 'Payment processing failed',
                'description' => 'errors.order.payment_failed',
                'type' => ErrorType::SERVER,
                'httpStatus' => HttpStatus::INTERNAL_SERVER_ERROR->value,
            ],
        };
    }
}
