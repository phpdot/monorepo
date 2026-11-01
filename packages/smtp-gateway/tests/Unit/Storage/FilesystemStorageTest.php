<?php

declare(strict_types=1);

namespace PHPdot\Mail\Gateway\Tests\Unit\Storage;

use PHPdot\Mail\Gateway\DataType\DTO\MessageEnvelope;
use PHPdot\Mail\Gateway\DataType\Enum\QueueState;
use PHPdot\Mail\Gateway\Exception\StorageException;
use PHPdot\Mail\Gateway\Storage\FilesystemStorage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FilesystemStorageTest extends TestCase
{
    private string $tempDir;
    private FilesystemStorage $storage;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/outbox_test_' . bin2hex(random_bytes(8));
        $this->storage = new FilesystemStorage($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    #[Test]
    public function createsDirectories(): void
    {
        foreach (QueueState::cases() as $state) {
            self::assertDirectoryExists($this->tempDir . '/' . $state->value);
        }
    }

    #[Test]
    public function storesAndRetrievesMessage(): void
    {
        $envelope = $this->createEnvelope('msg001');
        $eml = "From: test@test.com\r\nSubject: Test\r\n\r\nBody";

        $this->storage->store($envelope, $eml);

        $retrieved = $this->storage->retrieve('msg001');
        self::assertSame($eml, $retrieved);
    }

    #[Test]
    public function storesAndRetrievesEnvelope(): void
    {
        $envelope = $this->createEnvelope('msg002');
        $this->storage->store($envelope, 'test eml');

        $retrieved = $this->storage->getEnvelope('msg002');
        self::assertSame('msg002', $retrieved->id);
        self::assertSame('sender@test.com', $retrieved->sender);
        self::assertSame(['rcpt@test.com'], $retrieved->recipients);
        self::assertSame(QueueState::Pending, $retrieved->state);
    }

    #[Test]
    public function updatesEnvelopeState(): void
    {
        $envelope = $this->createEnvelope('msg003');
        $this->storage->store($envelope, 'test eml');

        $sent = $envelope->withState(QueueState::Sent);
        $this->storage->updateEnvelope($sent);

        $retrieved = $this->storage->getEnvelope('msg003');
        self::assertSame(QueueState::Sent, $retrieved->state);

        // .eml should have moved to sent directory
        self::assertFileExists($this->tempDir . '/sent/msg003.eml');
        self::assertFileDoesNotExist($this->tempDir . '/pending/msg003.eml');
    }

    #[Test]
    public function listsByState(): void
    {
        $this->storage->store($this->createEnvelope('a'), 'eml1');
        $this->storage->store($this->createEnvelope('b'), 'eml2');
        $this->storage->store($this->createEnvelope('c'), 'eml3');

        $ids = $this->storage->listByState(QueueState::Pending);
        self::assertCount(3, $ids);
        self::assertContains('a', $ids);
        self::assertContains('b', $ids);
        self::assertContains('c', $ids);
    }

    #[Test]
    public function listsByStateRespectsLimit(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->storage->store($this->createEnvelope("msg$i"), "eml$i");
        }

        $ids = $this->storage->listByState(QueueState::Pending, 3);
        self::assertCount(3, $ids);
    }

    #[Test]
    public function listsRetryable(): void
    {
        // Fresh message (no attempts, no nextRetryAt) — should be retryable
        $this->storage->store($this->createEnvelope('fresh'), 'eml');

        // Message with future retry — should NOT be retryable
        $futureRetry = new MessageEnvelope(
            id: 'future',
            sender: 'a@b.com',
            recipients: ['c@d.com'],
            size: 100,
            state: QueueState::Pending,
            authUser: 'user',
            clientIp: '127.0.0.1',
            receivedAt: new \DateTimeImmutable(),
            attempts: 1,
            lastError: 'timeout',
            nextRetryAt: new \DateTimeImmutable('+1 hour'),
        );
        $this->storage->store($futureRetry, 'eml');

        // Message with past retry — should be retryable
        $pastRetry = new MessageEnvelope(
            id: 'past',
            sender: 'a@b.com',
            recipients: ['c@d.com'],
            size: 100,
            state: QueueState::Pending,
            authUser: 'user',
            clientIp: '127.0.0.1',
            receivedAt: new \DateTimeImmutable(),
            attempts: 1,
            lastError: 'timeout',
            nextRetryAt: new \DateTimeImmutable('-1 minute'),
        );
        $this->storage->store($pastRetry, 'eml');

        $retryable = $this->storage->listRetryable();
        self::assertContains('fresh', $retryable);
        self::assertContains('past', $retryable);
        self::assertNotContains('future', $retryable);
    }

    #[Test]
    public function deletesMessage(): void
    {
        $this->storage->store($this->createEnvelope('del'), 'eml');
        $this->storage->delete('del');

        $this->expectException(StorageException::class);
        $this->storage->retrieve('del');
    }

    #[Test]
    public function statsCountsByState(): void
    {
        $this->storage->store($this->createEnvelope('a'), 'eml1');
        $this->storage->store($this->createEnvelope('b'), 'eml2');

        $sent = $this->createEnvelope('c')->withState(QueueState::Sent);
        $this->storage->store($sent, 'eml3');

        // Need to update 'c' to sent state directory
        $this->storage->updateEnvelope($sent);

        $stats = $this->storage->stats();
        // 'a' and 'b' are pending, 'c' moved to sent
        self::assertSame(2, $stats['pending']);
        self::assertSame(1, $stats['sent']);
    }

    #[Test]
    public function retrieveNonexistentThrows(): void
    {
        $this->expectException(StorageException::class);
        $this->storage->retrieve('nonexistent');
    }

    #[Test]
    public function retryTracking(): void
    {
        $envelope = $this->createEnvelope('retry');
        $this->storage->store($envelope, 'eml');

        $retried = $envelope->withRetry('Connection timeout', new \DateTimeImmutable('+30 seconds'));
        $this->storage->updateEnvelope($retried);

        $retrieved = $this->storage->getEnvelope('retry');
        self::assertSame(1, $retrieved->attempts);
        self::assertSame('Connection timeout', $retrieved->lastError);
        self::assertNotNull($retrieved->nextRetryAt);
    }

    // === Helpers ===

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

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        /** @var \RecursiveIteratorIterator<\RecursiveDirectoryIterator> $items */
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
