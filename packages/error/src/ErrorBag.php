<?php

declare(strict_types=1);

/**
 * Collects errors during a request or operation.
 *
 * Accumulates ErrorEntry objects from any module's error enum.
 * Same bag used in controllers, services, validators — everywhere.
 * Serialized identically for JSON API, HTML forms, WebSocket, CLI.
 *
 * Optionally accepts a `MessageTranslatorInterface`. When wired, every
 * `add()` call sets the entry's `description` field to the translator's
 * output for the original description key + ICU params. When no translator
 * is wired, `description` stays as the raw key. The `message` field is
 * always the enum's English string. The bag does not second-guess the
 * translator: whatever the translator returns is what the entry carries.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Error;

use PHPdot\Contracts\I18n\MessageTranslatorInterface;

final class ErrorBag implements \Countable
{
    /**
     * @var list<ErrorEntry>
     */
    private array $errors = [];

    /**
     * __construct.
     *
     * @param ?MessageTranslatorInterface $translator
     */
    public function __construct(
        private readonly ?MessageTranslatorInterface $translator = null,
    ) {}

    /**
     * Add an error from an ErrorCodeInterface enum.
     *
     * @param ErrorCodeInterface $error The error enum case
     * @param string|null $context What this relates to (field, param, header, service, etc.)
     * @param array<string, mixed> $params ICU interpolation params for translation
     *
     * @return self
     */
    public function add(ErrorCodeInterface $error, ?string $context = null, array $params = []): self
    {
        $details = $error->getDetails();

        $description = $this->translator !== null
            ? $this->translator->translate($details['description'], $params)
            : $details['description'];

        $this->errors[] = new ErrorEntry(
            code: $error->getCode(),
            message: $details['message'],
            description: $description,
            type: $details['type'],
            httpStatus: $details['httpStatus'],
            context: $context,
            params: $params,
        );

        return $this;
    }

    /**
     * Add a raw ErrorEntry directly.
     *
     * @param ErrorEntry $entry
     *
     * @return ErrorBag
     */
    public function addEntry(ErrorEntry $entry): self
    {
        $this->errors[] = $entry;

        return $this;
    }

    /**
     * Check if any errors have been added.
     *
     * @return bool
     */
    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * Check if a specific error code is in the bag.
     *
     * @param ErrorCodeInterface $error
     *
     * @return bool
     */
    public function hasError(ErrorCodeInterface $error): bool
    {
        $code = $error->getCode();

        foreach ($this->errors as $entry) {
            if ($entry->code === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all errors.
     *
     * @return list<ErrorEntry>
     */
    public function all(): array
    {
        return $this->errors;
    }

    /**
     * Get the first error, or null if empty.
     *
     * @return ?ErrorEntry
     */
    public function first(): ?ErrorEntry
    {
        return $this->errors[0] ?? null;
    }

    /**
     * Get errors for a specific context.
     *
     * @param string $context
     *
     * @return list<ErrorEntry>
     */
    public function forContext(string $context): array
    {
        return array_values(
            array_filter($this->errors, static fn(ErrorEntry $e): bool => $e->context === $context),
        );
    }

    /**
     * Get errors of a specific type.
     *
     * @param ErrorType $type
     *
     * @return list<ErrorEntry>
     */
    public function ofType(ErrorType $type): array
    {
        return array_values(
            array_filter($this->errors, static fn(ErrorEntry $e): bool => $e->type === $type),
        );
    }

    /**
     * Get error count.
     */
    public function count(): int
    {
        return count($this->errors);
    }

    /**
     * Merge another ErrorBag into this one.
     *
     * @param self $other
     *
     * @return ErrorBag
     */
    public function merge(self $other): self
    {
        $this->errors = [...$this->errors, ...$other->errors];

        return $this;
    }

    /**
     * Clear all errors.
     *
     * @return ErrorBag
     */
    public function clear(): self
    {
        $this->errors = [];

        return $this;
    }

    /**
     * Get the HTTP status code derived from collected errors.
     *
     * Uses the first error's status. If empty, returns 500.
     *
     * @return int
     */
    public function getHttpStatus(): int
    {
        if ($this->errors === []) {
            return 500;
        }

        return $this->errors[0]->httpStatus;
    }

    /**
     * Get all unique error codes.
     *
     * @return list<string>
     */
    public function codes(): array
    {
        return array_values(array_unique(
            array_map(static fn(ErrorEntry $e): string => $e->code, $this->errors),
        ));
    }

    /**
     * Convert all errors to arrays.
     *
     * @return list<array{
     *     code: string,
     *     message: string,
     *     description: string,
     *     type: string,
     *     httpStatus: int,
     *     context: string|null,
     *     params: array<string, mixed>,
     * }>
     */
    public function toArray(): array
    {
        return array_map(static fn(ErrorEntry $e): array => $e->toArray(), $this->errors);
    }
}
