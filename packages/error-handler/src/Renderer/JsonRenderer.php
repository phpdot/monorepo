<?php

declare(strict_types=1);

/**
 * RFC 9457 Problem Details JSON renderer.
 *
 * In development: includes exception class, message, file, line, and full trace.
 * In production: returns safe, generic error messages.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\ErrorHandler\Renderer;

use PHPdot\ErrorHandler\Context\ErrorContext;
use PHPdot\ErrorHandler\Contract\RendererInterface;

final class JsonRenderer implements RendererInterface
{
    public function render(ErrorContext $context): string
    {
        $body = [
            'type' => 'about:blank',
            'title' => self::getTitle($context->statusCode),
            'status' => $context->statusCode,
            'detail' => $context->isDevelopment
                ? $context->exception->getMessage()
                : self::getSafeMessage($context->statusCode),
        ];

        if ($context->isDevelopment) {
            $body['exception'] = [
                'class' => $context->exception::class,
                'message' => $context->exception->getMessage(),
                'file' => $context->exception->getFile(),
                'line' => $context->exception->getLine(),
                'trace' => array_map(static fn($f): array => [
                    'file' => $f->file,
                    'line' => $f->line,
                    'class' => $f->class,
                    'function' => $f->function,
                ], $context->stackTrace->frames),
            ];

            if ($context->solutions !== []) {
                $body['solutions'] = array_map(static fn($s): array => [
                    'title' => $s->title,
                    'description' => $s->description,
                ], $context->solutions);
            }
        }

        return json_encode($body, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Map an HTTP status code to its short problem title.
     *
     * @param int $statusCode HTTP status code
     *
     * @return string
     */
    private static function getTitle(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            default => 'Error',
        };
    }

    /**
     * Return a generic, internals-free detail message for a status code.
     *
     * @param int $statusCode HTTP status code
     *
     * @return string
     */
    private static function getSafeMessage(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'The request could not be understood.',
            401 => 'Authentication is required.',
            403 => 'You do not have permission to access this resource.',
            404 => 'The requested resource was not found.',
            405 => 'The request method is not supported.',
            422 => 'The request data is invalid.',
            429 => 'Too many requests. Please try again later.',
            500 => 'An unexpected error occurred.',
            503 => 'The service is temporarily unavailable.',
            default => 'An error occurred.',
        };
    }
}
