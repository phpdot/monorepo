<?php

declare(strict_types=1);

/**
 * Storage interface for queued email messages.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Storage;

use PHPdot\Mail\Gateway\DataType\DTO\MessageEnvelope;
use PHPdot\Mail\Gateway\DataType\Enum\QueueState;

/**
 * Pluggable storage backend for .eml files and metadata.
 *
 * Ships with FilesystemStorage. Implement this for S3, Redis, database, etc.
 */
interface StorageInterface
{
    /**
     * Store a raw .eml message with metadata.
     *
     * @throws \PHPdot\Mail\Gateway\Exception\StorageException
     */
    public function store(MessageEnvelope $envelope, string $eml): void;

    /**
     * Retrieve the raw .eml content by message ID.
     *
     * @throws \PHPdot\Mail\Gateway\Exception\StorageException
     */
    public function retrieve(string $id): string;

    /**
     * Retrieve the metadata envelope by message ID.
     *
     * @throws \PHPdot\Mail\Gateway\Exception\StorageException
     */
    public function getEnvelope(string $id): MessageEnvelope;

    /**
     * Update the envelope metadata (state, retry count, etc.).
     *
     * @throws \PHPdot\Mail\Gateway\Exception\StorageException
     */
    public function updateEnvelope(MessageEnvelope $envelope): void;

    /**
     * List message IDs by queue state.
     *
     * @throws \PHPdot\Mail\Gateway\Exception\StorageException
     *
     * @return list<string>
     */
    public function listByState(QueueState $state, int $limit = 100): array;

    /**
     * List message IDs that are ready for retry (nextRetryAt <= now).
     *
     * @throws \PHPdot\Mail\Gateway\Exception\StorageException
     *
     * @return list<string>
     */
    public function listRetryable(int $limit = 100): array;

    /**
     * Delete a message and its metadata.
     *
     * @throws \PHPdot\Mail\Gateway\Exception\StorageException
     */
    public function delete(string $id): void;

    /**
     * Count messages by state.
     *
     * @throws \PHPdot\Mail\Gateway\Exception\StorageException
     *
     * @return array<string, int> Map of state => count
     */
    public function stats(): array;
}
