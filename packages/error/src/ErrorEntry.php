<?php

declare(strict_types=1);

/**
 * A single error entry. Pure data — no translation, no escaping.
 *
 * Translation and escaping happen at render time, not at creation time.
 * The description is a translation key (e.g., 'errors.user.not_found'),
 * not translated text.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Error;

final readonly class ErrorEntry
{
    /**
     * Create one immutable structured error entry.
     *
     * @param string $code Unique error code (e.g., '00010001')
     * @param string $message English fallback message
     * @param string $description Translation key (e.g., 'errors.user.not_found')
     * @param ErrorType $type Error category
     * @param int $httpStatus HTTP status code
     * @param string|null $context What this error relates to (field, param, header, service, path)
     * @param array<string, mixed> $params ICU interpolation params for translation
     */
    public function __construct(
        public string $code,
        public string $message,
        public string $description,
        public ErrorType $type,
        public int $httpStatus,
        public ?string $context = null,
        public array $params = [],
    ) {}

    /**
     * Convert to array for serialization.
     *
     * @return array{
     *     code: string,
     *     message: string,
     *     description: string,
     *     type: string,
     *     httpStatus: int,
     *     context: string|null,
     *     params: array<string, mixed>,
     * }
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'description' => $this->description,
            'type' => $this->type->value,
            'httpStatus' => $this->httpStatus,
            'context' => $this->context,
            'params' => $this->params,
        ];
    }
}
