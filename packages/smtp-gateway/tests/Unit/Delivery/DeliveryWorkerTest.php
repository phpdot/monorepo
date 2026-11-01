<?php

declare(strict_types=1);

namespace PHPdot\Mail\Gateway\Tests\Unit\Delivery;

use PHPdot\Mail\Gateway\DataType\DTO\MessageEnvelope;
use PHPdot\Mail\Gateway\DataType\Enum\QueueState;
use PHPdot\Mail\Gateway\Delivery\DeliveryWorker;
use PHPdot\Mail\Gateway\Delivery\TransportInterface;
use PHPdot\Mail\Gateway\Exception\DeliveryException;
use PHPdot\Mail\Gateway\Exception\StorageException;
use PHPdot\Mail\Gateway\Storage\StorageInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DeliveryWorkerTest extends TestCase
{
    private StorageInterface&\PHPUnit\Framework\MockObject\MockObject $storage;
    private TransportInterface&\PHPUnit\Framework\MockObject\MockObject $transport;

    protected function setUp(): void
    {
        $this->storage = $this->createMock(StorageInterface::class);
        $this->transport = $this->createMock(TransportInterface::class);
    }

    #[Test]
    public function processBatchReturnsZeroWhenNoMessages(): void
    {
        $this->storage->method('listRetryable')->willReturn([]);

        $worker = new DeliveryWorker($this->storage, $this->transport);
        self::assertSame(0, $worker->processBatch());
    }

    #[Test]
    public function processBatchDeliversMessages(): void
    {
        $envelope = $this->createEnvelope('msg1');

        $this->storage->method('listRetryable')->willReturn(['msg1']);
        $this->storage->method('getEnvelope')->willReturn($envelope);
        $this->storage->method('retrieve')->willReturn('raw eml content');
        $this->storage->expects(self::atLeast(2))->method('updateEnvelope');

        $this->transport->method('send')->willReturn('provider-id-123');
        $this->transport->method('name')->willReturn('test');

        $worker = new DeliveryWorker($this->storage, $this->transport);
        self::assertSame(1, $worker->processBatch());
    }

    #[Test]
    public function processOneMarksAsSendingBeforeDelivery(): void
    {
        $envelope = $this->createEnvelope('msg1');
        $states = [];

        $this->storage->method('getEnvelope')->willReturn($envelope);
        $this->storage->method('retrieve')->willReturn('raw eml');
        $this->storage->method('updateEnvelope')
            ->willReturnCallback(function (MessageEnvelope $e) use (&$states): void {
                $states[] = $e->state;
            });

        $this->transport->method('send')->willReturn('id');
        $this->transport->method('name')->willReturn('test');

        $worker = new DeliveryWorker($this->storage, $this->transport);
        $worker->processOne('msg1');

        self::assertSame(QueueState::Sending, $states[0]);
        self::assertSame(QueueState::Sent, $states[1]);
    }

    #[Test]
    public function onDeliveredCallbackFired(): void
    {
        $envelope = $this->createEnvelope('msg1');

        $this->storage->method('getEnvelope')->willReturn($envelope);
        $this->storage->method('retrieve')->willReturn('eml');

        $this->transport->method('send')->willReturn('prov-id');
        $this->transport->method('name')->willReturn('test-transport');

        $deliveredId = null;
        $deliveredProviderId = null;
        $deliveredTransport = null;

        $worker = new DeliveryWorker($this->storage, $this->transport);
        $worker->onDelivered(function (string $id, string $provId, string $name) use (&$deliveredId, &$deliveredProviderId, &$deliveredTransport): void {
            $deliveredId = $id;
            $deliveredProviderId = $provId;
            $deliveredTransport = $name;
        });

        $worker->processOne('msg1');

        self::assertSame('msg1', $deliveredId);
        self::assertSame('prov-id', $deliveredProviderId);
        self::assertSame('test-transport', $deliveredTransport);
    }

    #[Test]
    public function deliveryFailureTriggersRetry(): void
    {
        $envelope = $this->createEnvelope('msg1');
        $updatedEnvelopes = [];

        $this->storage->method('getEnvelope')->willReturn($envelope);
        $this->storage->method('retrieve')->willReturn('eml');
        $this->storage->method('updateEnvelope')
            ->willReturnCallback(function (MessageEnvelope $e) use (&$updatedEnvelopes): void {
                $updatedEnvelopes[] = $e;
            });

        $this->transport->method('send')
            ->willThrowException(new DeliveryException('Connection timeout'));

        $retryError = null;
        $retryAttempts = null;

        $worker = new DeliveryWorker($this->storage, $this->transport);
        $worker->onRetry(function (string $id, string $error, int $attempts) use (&$retryError, &$retryAttempts): void {
            $retryError = $error;
            $retryAttempts = $attempts;
        });

        $worker->processOne('msg1');

        self::assertSame('Connection timeout', $retryError);
        self::assertSame(1, $retryAttempts);

        // Last update should be Pending (retry)
        $lastEnvelope = end($updatedEnvelopes);
        self::assertInstanceOf(MessageEnvelope::class, $lastEnvelope);
        self::assertSame(QueueState::Pending, $lastEnvelope->state);
        self::assertSame(1, $lastEnvelope->attempts);
        self::assertNotNull($lastEnvelope->nextRetryAt);
    }

    #[Test]
    public function maxRetriesExceededMarksFailed(): void
    {
        $envelope = new MessageEnvelope(
            id: 'msg1',
            sender: 'a@b.com',
            recipients: ['c@d.com'],
            size: 100,
            state: QueueState::Sending,
            authUser: 'user',
            clientIp: '127.0.0.1',
            receivedAt: new \DateTimeImmutable(),
            attempts: 6,
        );

        $this->storage->method('getEnvelope')->willReturn($envelope);
        $this->storage->method('retrieve')->willReturn('eml');

        $this->transport->method('send')
            ->willThrowException(new DeliveryException('fail'));

        $failedId = null;
        $failedError = null;
        $failedAttempts = null;

        $worker = new DeliveryWorker($this->storage, $this->transport, maxRetries: 7);
        $worker->onFailed(function (string $id, string $error, int $attempts) use (&$failedId, &$failedError, &$failedAttempts): void {
            $failedId = $id;
            $failedError = $error;
            $failedAttempts = $attempts;
        });

        $worker->processOne('msg1');

        self::assertSame('msg1', $failedId);
        self::assertSame('fail', $failedError);
        self::assertSame(7, $failedAttempts);
    }

    #[Test]
    public function backoffDelaysIncrease(): void
    {
        $retryTimes = [];

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $envelope = new MessageEnvelope(
                id: 'msg1',
                sender: 'a@b.com',
                recipients: ['c@d.com'],
                size: 100,
                state: QueueState::Sending,
                authUser: 'user',
                clientIp: '127.0.0.1',
                receivedAt: new \DateTimeImmutable(),
                attempts: $attempt,
            );

            $storage = $this->createMock(StorageInterface::class);
            $transport = $this->createMock(TransportInterface::class);

            $storage->method('getEnvelope')->willReturn($envelope);
            $storage->method('retrieve')->willReturn('eml');
            $storage->method('updateEnvelope')
                ->willReturnCallback(function (MessageEnvelope $e) use (&$retryTimes): void {
                    if ($e->nextRetryAt !== null) {
                        $retryTimes[] = $e->nextRetryAt;
                    }
                });

            $transport->method('send')
                ->willThrowException(new DeliveryException('fail'));

            $worker = new DeliveryWorker($storage, $transport, maxRetries: 7);
            $worker->processOne('msg1');
        }

        self::assertCount(6, $retryTimes);

        // Verify delays increase monotonically
        $now = new \DateTimeImmutable();
        $previousDelay = 0;
        foreach ($retryTimes as $retryTime) {
            $delay = $retryTime->getTimestamp() - $now->getTimestamp();
            self::assertGreaterThanOrEqual($previousDelay, $delay);
            $previousDelay = $delay;
        }
    }

    #[Test]
    public function processOneSkipsWhenEnvelopeNotFound(): void
    {
        $this->storage->method('getEnvelope')
            ->willThrowException(new StorageException('not found'));

        $this->transport->expects(self::never())->method('send');

        $worker = new DeliveryWorker($this->storage, $this->transport);
        $worker->processOne('missing');
    }

    #[Test]
    public function processOneSkipsWhenEmlRetrieveFails(): void
    {
        $envelope = $this->createEnvelope('msg1');

        $this->storage->method('getEnvelope')->willReturn($envelope);
        $this->storage->method('retrieve')
            ->willThrowException(new StorageException('read error'));

        $this->transport->expects(self::never())->method('send');

        $worker = new DeliveryWorker($this->storage, $this->transport);
        $worker->processOne('msg1');
    }

    #[Test]
    public function processBatchRespectsLimit(): void
    {
        $this->storage->method('listRetryable')
            ->with(5)
            ->willReturn(['a', 'b', 'c', 'd', 'e']);

        $this->storage->method('getEnvelope')
            ->willReturn($this->createEnvelope('x'));
        $this->storage->method('retrieve')->willReturn('eml');

        $this->transport->method('send')->willReturn('id');
        $this->transport->method('name')->willReturn('t');

        $worker = new DeliveryWorker($this->storage, $this->transport, batchSize: 10);
        $count = $worker->processBatch(5);
        self::assertSame(5, $count);
    }

    #[Test]
    public function unexpectedExceptionTriggersRetry(): void
    {
        $envelope = $this->createEnvelope('msg1');

        $this->storage->method('getEnvelope')->willReturn($envelope);
        $this->storage->method('retrieve')->willReturn('eml');

        $this->transport->method('send')
            ->willThrowException(new \RuntimeException('unexpected'));

        $retryError = null;

        $worker = new DeliveryWorker($this->storage, $this->transport);
        $worker->onRetry(function (string $id, string $error, int $attempts) use (&$retryError): void {
            $retryError = $error;
        });

        $worker->processOne('msg1');

        self::assertNotNull($retryError);
        self::assertStringContainsString('Unexpected error', $retryError);
    }

    #[Test]
    public function stopHaltsWorker(): void
    {
        $worker = new DeliveryWorker($this->storage, $this->transport);
        self::assertFalse($worker->isRunning());

        // Can't test run() directly (it blocks), but we can test state
        $worker->stop();
        self::assertFalse($worker->isRunning());
    }

    private function createEnvelope(string $id): MessageEnvelope
    {
        return new MessageEnvelope(
            id: $id,
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
