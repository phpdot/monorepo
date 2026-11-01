<?php

declare(strict_types=1);

namespace PHPdot\Mail\Gateway\Tests\Unit\DataType;

use PHPdot\Mail\Gateway\DataType\DTO\MessageEnvelope;
use PHPdot\Mail\Gateway\DataType\Enum\QueueState;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MessageEnvelopeTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        $now = new \DateTimeImmutable();
        $envelope = new MessageEnvelope(
            id: 'msg1',
            sender: 'from@test.com',
            recipients: ['to@test.com'],
            size: 2048,
            state: QueueState::Pending,
            authUser: 'omar',
            clientIp: '10.0.0.1',
            receivedAt: $now,
        );

        self::assertSame('msg1', $envelope->id);
        self::assertSame('from@test.com', $envelope->sender);
        self::assertSame(['to@test.com'], $envelope->recipients);
        self::assertSame(2048, $envelope->size);
        self::assertSame(QueueState::Pending, $envelope->state);
        self::assertSame('omar', $envelope->authUser);
        self::assertSame('10.0.0.1', $envelope->clientIp);
        self::assertSame($now, $envelope->receivedAt);
        self::assertSame(0, $envelope->attempts);
        self::assertNull($envelope->lastError);
        self::assertNull($envelope->nextRetryAt);
        self::assertNull($envelope->sentAt);
    }

    #[Test]
    public function toArraySerializesAllFields(): void
    {
        $now = new \DateTimeImmutable('2025-06-15T10:00:00+00:00');
        $envelope = new MessageEnvelope(
            id: 'msg1',
            sender: 'a@b.com',
            recipients: ['c@d.com', 'e@f.com'],
            size: 1024,
            state: QueueState::Pending,
            authUser: 'user',
            clientIp: '127.0.0.1',
            receivedAt: $now,
            attempts: 2,
            lastError: 'timeout',
            nextRetryAt: $now,
        );

        $arr = $envelope->toArray();

        self::assertSame('msg1', $arr['id']);
        self::assertSame('a@b.com', $arr['sender']);
        self::assertSame(['c@d.com', 'e@f.com'], $arr['recipients']);
        self::assertSame(1024, $arr['size']);
        self::assertSame('pending', $arr['state']);
        self::assertSame('user', $arr['auth_user']);
        self::assertSame('127.0.0.1', $arr['client_ip']);
        self::assertSame(2, $arr['attempts']);
        self::assertSame('timeout', $arr['last_error']);
        self::assertNotNull($arr['received_at']);
        self::assertNotNull($arr['next_retry_at']);
        self::assertNull($arr['sent_at']);
    }

    #[Test]
    public function fromArrayDeserializesCorrectly(): void
    {
        $data = [
            'id' => 'msg1',
            'sender' => 'a@b.com',
            'recipients' => ['c@d.com'],
            'size' => 1024,
            'state' => 'pending',
            'auth_user' => 'user',
            'client_ip' => '127.0.0.1',
            'received_at' => '2025-06-15T10:00:00+00:00',
            'attempts' => 3,
            'last_error' => 'error msg',
            'next_retry_at' => '2025-06-15T10:05:00+00:00',
            'sent_at' => null,
        ];

        $envelope = MessageEnvelope::fromArray($data);

        self::assertSame('msg1', $envelope->id);
        self::assertSame('a@b.com', $envelope->sender);
        self::assertSame(['c@d.com'], $envelope->recipients);
        self::assertSame(1024, $envelope->size);
        self::assertSame(QueueState::Pending, $envelope->state);
        self::assertSame(3, $envelope->attempts);
        self::assertSame('error msg', $envelope->lastError);
        self::assertNotNull($envelope->nextRetryAt);
        self::assertNull($envelope->sentAt);
    }

    #[Test]
    public function fromArrayRoundTrip(): void
    {
        $now = new \DateTimeImmutable('2025-06-15T10:00:00+00:00');
        $original = new MessageEnvelope(
            id: 'msg1',
            sender: 'a@b.com',
            recipients: ['c@d.com'],
            size: 1024,
            state: QueueState::Sent,
            authUser: 'user',
            clientIp: '127.0.0.1',
            receivedAt: $now,
            attempts: 1,
            sentAt: $now,
        );

        $restored = MessageEnvelope::fromArray($original->toArray());

        self::assertSame($original->id, $restored->id);
        self::assertSame($original->sender, $restored->sender);
        self::assertSame($original->recipients, $restored->recipients);
        self::assertSame($original->size, $restored->size);
        self::assertSame($original->state, $restored->state);
        self::assertSame($original->attempts, $restored->attempts);
        self::assertNotNull($restored->sentAt);
    }

    #[Test]
    public function withStateTransitionsSetsState(): void
    {
        $envelope = $this->createEnvelope();
        $sending = $envelope->withState(QueueState::Sending);

        self::assertSame(QueueState::Sending, $sending->state);
        self::assertSame(QueueState::Pending, $envelope->state); // Original unchanged
    }

    #[Test]
    public function withStateSentSetsSentAt(): void
    {
        $envelope = $this->createEnvelope();
        $sent = $envelope->withState(QueueState::Sent);

        self::assertNotNull($sent->sentAt);
    }

    #[Test]
    public function withStateSentDoesNotOverwriteExistingSentAt(): void
    {
        $originalTime = new \DateTimeImmutable('2025-01-01T00:00:00+00:00');
        $envelope = new MessageEnvelope(
            id: 'msg1',
            sender: 'a@b.com',
            recipients: ['c@d.com'],
            size: 100,
            state: QueueState::Sending,
            authUser: 'user',
            clientIp: '127.0.0.1',
            receivedAt: new \DateTimeImmutable(),
            sentAt: $originalTime,
        );

        $sent = $envelope->withState(QueueState::Sent);
        self::assertSame($originalTime, $sent->sentAt);
    }

    #[Test]
    public function withStatePreservesOtherFields(): void
    {
        $envelope = $this->createEnvelope();
        $sending = $envelope->withState(QueueState::Sending);

        self::assertSame($envelope->id, $sending->id);
        self::assertSame($envelope->sender, $sending->sender);
        self::assertSame($envelope->recipients, $sending->recipients);
        self::assertSame($envelope->size, $sending->size);
        self::assertSame($envelope->authUser, $sending->authUser);
        self::assertSame($envelope->clientIp, $sending->clientIp);
        self::assertSame($envelope->receivedAt, $sending->receivedAt);
        self::assertSame($envelope->attempts, $sending->attempts);
    }

    #[Test]
    public function withRetryIncrementsAttemptsAndSchedules(): void
    {
        $envelope = $this->createEnvelope();
        $nextRetry = new \DateTimeImmutable('+30 seconds');
        $retried = $envelope->withRetry('Connection timeout', $nextRetry);

        self::assertSame(QueueState::Pending, $retried->state);
        self::assertSame(1, $retried->attempts);
        self::assertSame('Connection timeout', $retried->lastError);
        self::assertSame($nextRetry, $retried->nextRetryAt);
    }

    #[Test]
    public function withRetryPreservesOriginal(): void
    {
        $envelope = $this->createEnvelope();
        $envelope->withRetry('error', new \DateTimeImmutable());

        self::assertSame(0, $envelope->attempts);
        self::assertNull($envelope->lastError);
        self::assertNull($envelope->nextRetryAt);
    }

    #[Test]
    public function fromArrayHandlesMissingOptionalFields(): void
    {
        $data = [
            'id' => 'msg1',
            'sender' => 'a@b.com',
            'recipients' => ['c@d.com'],
            'size' => 100,
            'state' => 'pending',
            'auth_user' => 'user',
            'client_ip' => '127.0.0.1',
            'received_at' => '2025-01-01T00:00:00+00:00',
        ];

        $envelope = MessageEnvelope::fromArray($data);

        self::assertSame(0, $envelope->attempts);
        self::assertNull($envelope->lastError);
        self::assertNull($envelope->nextRetryAt);
        self::assertNull($envelope->sentAt);
    }

    private function createEnvelope(): MessageEnvelope
    {
        return new MessageEnvelope(
            id: 'msg1',
            sender: 'sender@test.com',
            recipients: ['rcpt@test.com'],
            size: 1024,
            state: QueueState::Pending,
            authUser: 'testuser',
            clientIp: '127.0.0.1',
            receivedAt: new \DateTimeImmutable(),
        );
    }
}
