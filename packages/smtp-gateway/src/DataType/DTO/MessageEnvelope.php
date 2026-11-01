<?php

declare(strict_types=1);

/**
 * Metadata envelope for a queued email message.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\DataType\DTO;

use PHPdot\Mail\Gateway\DataType\Enum\QueueState;

/**
 * Tracks everything about a queued message except the raw .eml content.
 */
readonly class MessageEnvelope
{
    /**
     * @param list<string> $recipients
     */
    public function __construct(
        public string $id,
        public string $sender,
        public array $recipients,
        public int $size,
        public QueueState $state,
        public string $authUser,
        public string $clientIp,
        public \DateTimeImmutable $receivedAt,
        public int $attempts = 0,
        public string|null $lastError = null,
        public \DateTimeImmutable|null $nextRetryAt = null,
        public \DateTimeImmutable|null $sentAt = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $id = is_string($data['id'] ?? null) ? $data['id'] : '';
        $sender = is_string($data['sender'] ?? null) ? $data['sender'] : '';
        /** @var list<string> $recipients */
        $recipients = is_array($data['recipients'] ?? null) ? array_values($data['recipients']) : [];
        $sizeRaw = $data['size'] ?? 0;
        $size = is_int($sizeRaw) ? $sizeRaw : (is_string($sizeRaw) ? (int) $sizeRaw : 0);
        $state = is_string($data['state'] ?? null) ? $data['state'] : 'pending';
        $authUser = is_string($data['auth_user'] ?? null) ? $data['auth_user'] : '';
        $clientIp = is_string($data['client_ip'] ?? null) ? $data['client_ip'] : '';
        $receivedAt = is_string($data['received_at'] ?? null) ? $data['received_at'] : 'now';
        $attemptsRaw = $data['attempts'] ?? 0;
        $attempts = is_int($attemptsRaw) ? $attemptsRaw : (is_string($attemptsRaw) ? (int) $attemptsRaw : 0);
        $lastError = is_string($data['last_error'] ?? null) ? $data['last_error'] : null;
        $nextRetryAt = is_string($data['next_retry_at'] ?? null) ? $data['next_retry_at'] : null;
        $sentAt = is_string($data['sent_at'] ?? null) ? $data['sent_at'] : null;

        return new self(
            id: $id,
            sender: $sender,
            recipients: $recipients,
            size: $size,
            state: QueueState::from($state),
            authUser: $authUser,
            clientIp: $clientIp,
            receivedAt: new \DateTimeImmutable($receivedAt),
            attempts: $attempts,
            lastError: $lastError,
            nextRetryAt: $nextRetryAt !== null ? new \DateTimeImmutable($nextRetryAt) : null,
            sentAt: $sentAt !== null ? new \DateTimeImmutable($sentAt) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sender' => $this->sender,
            'recipients' => $this->recipients,
            'size' => $this->size,
            'state' => $this->state->value,
            'auth_user' => $this->authUser,
            'client_ip' => $this->clientIp,
            'received_at' => $this->receivedAt->format('c'),
            'attempts' => $this->attempts,
            'last_error' => $this->lastError,
            'next_retry_at' => $this->nextRetryAt?->format('c'),
            'sent_at' => $this->sentAt?->format('c'),
        ];
    }

    public function withState(QueueState $state): self
    {
        return new self(
            id: $this->id,
            sender: $this->sender,
            recipients: $this->recipients,
            size: $this->size,
            state: $state,
            authUser: $this->authUser,
            clientIp: $this->clientIp,
            receivedAt: $this->receivedAt,
            attempts: $this->attempts,
            lastError: $this->lastError,
            nextRetryAt: $this->nextRetryAt,
            sentAt: $state === QueueState::Sent && $this->sentAt === null ? new \DateTimeImmutable() : $this->sentAt,
        );
    }

    public function withRetry(string $error, \DateTimeImmutable $nextRetry): self
    {
        return new self(
            id: $this->id,
            sender: $this->sender,
            recipients: $this->recipients,
            size: $this->size,
            state: QueueState::Pending,
            authUser: $this->authUser,
            clientIp: $this->clientIp,
            receivedAt: $this->receivedAt,
            attempts: $this->attempts + 1,
            lastError: $error,
            nextRetryAt: $nextRetry,
            sentAt: $this->sentAt,
        );
    }
}
