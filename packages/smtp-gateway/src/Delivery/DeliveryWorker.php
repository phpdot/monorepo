<?php

declare(strict_types=1);

/**
 * Queue processor — picks up pending messages and delivers them.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Delivery;

use PHPdot\Mail\Gateway\DataType\Enum\QueueState;
use PHPdot\Mail\Gateway\Exception\DeliveryException;
use PHPdot\Mail\Gateway\Exception\StorageException;
use PHPdot\Mail\Gateway\Storage\StorageInterface;

/**
 * Processes the message queue: picks up pending/retryable messages and delivers them.
 *
 * Usage:
 *   $worker = new DeliveryWorker($storage, $transport);
 *   $worker->processBatch(10);       // Process up to 10 messages
 *   $worker->run();                   // Run continuously
 */
final class DeliveryWorker
{
    /** @var int[] Backoff delays in seconds: attempt 1 = 30s, 2 = 120s, etc. */
    private const array BACKOFF_DELAYS = [30, 120, 300, 900, 1800, 3600, 7200];

    private int $maxRetries;
    private int $batchSize;
    private int $pollInterval;
    private bool $running = false;

    /** @var callable(string, string, string): void|null */
    private $onDelivered = null;

    /** @var callable(string, string, int): void|null */
    private $onFailed = null;

    /** @var callable(string, string, int): void|null */
    private $onRetry = null;

    /** @var int Seconds before a message stuck in Sending is recovered */
    private int $sendingTimeout;

    public function __construct(
        private readonly StorageInterface $storage,
        private readonly TransportInterface $transport,
        int $maxRetries = 7,
        int $batchSize = 10,
        int $pollInterval = 5,
        int $sendingTimeout = 600,
    ) {
        $this->maxRetries = $maxRetries;
        $this->batchSize = $batchSize;
        $this->pollInterval = $pollInterval;
        $this->sendingTimeout = $sendingTimeout;
    }

    /**
     * Process a batch of pending messages.
     *
     * @phpstan-impure Performs storage I/O and triggers delivery callbacks that may call stop().
     *
     * @return int Number of messages processed (delivered + failed)
     */
    public function processBatch(int $limit = 0): int
    {

        $this->recoverStuckMessages();

        $batchLimit = $limit > 0 ? $limit : $this->batchSize;
        $ids = $this->storage->listRetryable($batchLimit);
        $processed = 0;

        foreach ($ids as $id) {
            $this->processOne($id);
            $processed++;
        }

        return $processed;
    }

    /**
     * Run the worker continuously. Blocks until stop() is called.
     */
    public function run(): void
    {
        $this->running = true;

        do {
            $processed = $this->processBatch();

            if ($processed === 0) {
                sleep($this->pollInterval);
            }
        } while ($this->running);
    }

    /**
     * Signal the worker to stop after the current batch.
     */
    public function stop(): void
    {
        $this->running = false;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Process a single message.
     */
    public function processOne(string $id): void
    {
        try {
            $envelope = $this->storage->getEnvelope($id);
        } catch (StorageException) {
            return;
        }

        $sending = $envelope->withState(QueueState::Sending);
        try {
            $this->storage->updateEnvelope($sending);
        } catch (StorageException) {
            return;
        }

        try {
            $eml = $this->storage->retrieve($id);
        } catch (StorageException $e) {
            $this->markFailed($sending, 'Failed to retrieve .eml: ' . $e->getMessage());
            return;
        }

        try {
            $providerMessageId = $this->transport->send($eml, $envelope->sender, $envelope->recipients);

            $sent = $sending->withState(QueueState::Sent);
            $this->storage->updateEnvelope($sent);

            if ($this->onDelivered !== null) {
                ($this->onDelivered)($id, $providerMessageId, $this->transport->name());
            }
        } catch (DeliveryException $e) {
            $this->handleDeliveryFailure($sending, $e->getMessage());
        } catch (\Throwable $e) {
            $this->handleDeliveryFailure($sending, 'Unexpected error: ' . $e->getMessage());
        }
    }

    /**
     * Called when a message is delivered successfully.
     *
     * @param callable(string, string, string): void $callback fn(messageId, providerMessageId, transportName)
     */
    public function onDelivered(callable $callback): void
    {
        $this->onDelivered = $callback;
    }

    /**
     * Called when a message permanently fails (max retries exceeded).
     *
     * @param callable(string, string, int): void $callback fn(messageId, lastError, attempts)
     */
    public function onFailed(callable $callback): void
    {
        $this->onFailed = $callback;
    }

    /**
     * Called when a message is scheduled for retry.
     *
     * @param callable(string, string, int): void $callback fn(messageId, error, attemptNumber)
     */
    public function onRetry(callable $callback): void
    {
        $this->onRetry = $callback;
    }

    private function handleDeliveryFailure(\PHPdot\Mail\Gateway\DataType\DTO\MessageEnvelope $envelope, string $error): void
    {
        $attempts = $envelope->attempts + 1;

        if ($attempts >= $this->maxRetries) {
            $this->markFailed($envelope, $error);
            return;
        }

        $delayIndex = min($attempts - 1, count(self::BACKOFF_DELAYS) - 1);
        $delay = self::BACKOFF_DELAYS[$delayIndex];
        $nextRetry = new \DateTimeImmutable('+' . $delay . ' seconds');

        $retryEnvelope = $envelope->withRetry($error, $nextRetry);

        try {
            $this->storage->updateEnvelope($retryEnvelope);
        } catch (StorageException) {

            return;
        }

        if ($this->onRetry !== null) {
            ($this->onRetry)($envelope->id, $error, $attempts);
        }
    }

    /**
     * Recover messages stuck in Sending state (from crashed workers).
     * Moves them back to Pending so they can be retried.
     */
    private function recoverStuckMessages(): void
    {
        try {
            $sendingIds = $this->storage->listByState(QueueState::Sending, $this->batchSize);
        } catch (StorageException) {
            return;
        }

        $now = new \DateTimeImmutable();

        foreach ($sendingIds as $id) {
            try {
                $envelope = $this->storage->getEnvelope($id);

                $sendingDuration = $now->getTimestamp() - $envelope->receivedAt->getTimestamp();
                if ($envelope->attempts > 0) {

                    $sendingDuration = $this->sendingTimeout + 1;
                }

                if ($sendingDuration > $this->sendingTimeout) {
                    $retry = $envelope->withRetry(
                        'Recovered from stuck Sending state',
                        new \DateTimeImmutable(),
                    );
                    $this->storage->updateEnvelope($retry);
                }
            } catch (StorageException) {
                continue;
            }
        }
    }

    private function markFailed(\PHPdot\Mail\Gateway\DataType\DTO\MessageEnvelope $envelope, string $error): void
    {
        $failed = new \PHPdot\Mail\Gateway\DataType\DTO\MessageEnvelope(
            id: $envelope->id,
            sender: $envelope->sender,
            recipients: $envelope->recipients,
            size: $envelope->size,
            state: QueueState::Failed,
            authUser: $envelope->authUser,
            clientIp: $envelope->clientIp,
            receivedAt: $envelope->receivedAt,
            attempts: $envelope->attempts + 1,
            lastError: $error,
            nextRetryAt: null,
            sentAt: null,
        );

        try {
            $this->storage->updateEnvelope($failed);
        } catch (StorageException) {

        }

        if ($this->onFailed !== null) {
            ($this->onFailed)($envelope->id, $error, $failed->attempts);
        }
    }
}
