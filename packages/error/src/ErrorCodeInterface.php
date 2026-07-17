<?php

declare(strict_types=1);

/**
 * Interface for error code enums.
 *
 * Each module defines its own backed string enum implementing this interface.
 * The enum value IS the error code (e.g., '00010001').
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Error;

interface ErrorCodeInterface
{
    /**
     * Get the error code (the enum value).
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * Get the human-readable English message (fallback).
     *
     * @return string
     */
    public function getMessage(): string;

    /**
     * Get the translation key (e.g., 'errors.user.not_found').
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Get the error category.
     *
     * @return ErrorType
     */
    public function getType(): ErrorType;

    /**
     * Get the HTTP status code.
     *
     * @return int
     */
    public function getHttpStatus(): int;

    /**
     * Get all error details.
     *
     * @return array{
     *     message: string,
     *     description: string,
     *     type: ErrorType,
     *     httpStatus: int,
     * }
     */
    public function getDetails(): array;
}
