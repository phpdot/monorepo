<?php

declare(strict_types=1);

namespace PHPdot\Mail\Gateway\Tests\Unit\Delivery;

use DateTimeImmutable;
use PHPdot\Mail\Gateway\DataType\DTO\MessageEnvelope;
use PHPdot\Mail\Gateway\DataType\Enum\QueueState;
use PHPdot\Mail\Gateway\Delivery\DeliveryWorker;
use PHPdot\Mail\Gateway\Delivery\TransportInterface;
use PHPdot\Mail\Gateway\Exception\PermanentDeliveryException;
use PHPdot\Mail\Gateway\Storage\FilesystemStorage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The worker against a real filesystem queue: each message is sent once, whatever fails around the send.
 */
final class DeliveryWorkerQueueTest extends TestCase
{
    private string $dir;

    private FilesystemStorage $storage;

    /** @var list<string> */
    private array $sent = [];

    /** @var (callable(): void)|null */
    private $duringSend = null;

    private string|null $refusal = null;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/gateway_queue_' . bin2hex(random_bytes(8));
        $this->storage = new FilesystemStorage($this->dir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    #[Test]
    public function callbackErrorAfterSendingNeverSendsAgain(): void
    {
        $this->storage->store($this->envelope('aaa'), "x\r\n");
        $worker = new DeliveryWorker($this->storage, $this->transport());
        $worker->onDelivered(static function (): void {
            throw new RuntimeException('db down');
        });

        $surfaced = false;
        try {
            $worker->processOne('aaa');
        } catch (RuntimeException) {
            $surfaced = true;
        }
        $worker->processBatch();

        self::assertTrue($surfaced);
        self::assertSame(['aaa'], $this->sent);
        self::assertSame(QueueState::Sent, $this->storage->getEnvelope('aaa')->state);
    }

    #[Test]
    public function secondWorkerCannotSendAMessageAlreadyClaimed(): void
    {
        $this->storage->store($this->envelope('bbb', attempts: 1, nextRetryAt: new DateTimeImmutable('-1 second')), "x\r\n");
        $second = new DeliveryWorker($this->storage, $this->transport());
        $this->duringSend = static function () use ($second): void {
            $second->processBatch();
        };

        (new DeliveryWorker($this->storage, $this->transport()))->processOne('bbb');

        self::assertSame(['bbb'], $this->sent);
    }

    #[Test]
    public function permanentRefusalFailsTheMessageWithoutRetry(): void
    {
        $this->storage->store($this->envelope('ccc'), "x\r\n");
        $this->refusal = '550 no such user';
        $failed = [];
        $worker = new DeliveryWorker($this->storage, $this->transport());
        $worker->onFailed(static function (string $id) use (&$failed): void {
            $failed[] = $id;
        });

        $worker->processOne('ccc');

        self::assertSame(['ccc'], $failed);
        self::assertSame(QueueState::Failed, $this->storage->getEnvelope('ccc')->state);
    }

    #[Test]
    public function stuckMessageIsRecoveredOnlyAfterTheTimeoutFromItsClaim(): void
    {
        $this->storage->store($this->envelope('ddd', attempts: 3, receivedAt: new DateTimeImmutable('-1 day')), "x\r\n");
        $this->storage->updateEnvelope($this->storage->getEnvelope('ddd')->withState(QueueState::Sending));

        (new DeliveryWorker($this->storage, $this->transport(), sendingTimeout: 600))->processBatch();

        self::assertSame(QueueState::Sending, $this->storage->getEnvelope('ddd')->state);
        self::assertSame([], $this->sent);
    }

    private function transport(): TransportInterface
    {
        return new class ($this) implements TransportInterface {
            public function __construct(private readonly DeliveryWorkerQueueTest $test) {}

            public function send(string $eml, string $sender, array $recipients): string
            {
                return $this->test->sending($sender);
            }

            public function name(): string
            {
                return 'recording';
            }
        };
    }

    /**
     * The recording transport's send: refuse when told to, else note the message and run the mid-send hook.
     */
    public function sending(string $sender): string
    {
        if ($this->refusal !== null) {
            throw new PermanentDeliveryException($this->refusal);
        }

        $this->sent[] = explode('@', $sender)[0];
        $hook = $this->duringSend;
        $this->duringSend = null;

        if ($hook !== null) {
            $hook();
        }

        return 'provider-' . count($this->sent);
    }

    private function envelope(
        string $id,
        int $attempts = 0,
        DateTimeImmutable|null $nextRetryAt = null,
        DateTimeImmutable|null $receivedAt = null,
    ): MessageEnvelope {
        return new MessageEnvelope(
            id: $id,
            sender: $id . '@sender.test',
            recipients: ['to@rcpt.test'],
            size: 3,
            state: QueueState::Pending,
            authUser: 'u',
            clientIp: '127.0.0.1',
            receivedAt: $receivedAt ?? new DateTimeImmutable(),
            attempts: $attempts,
            nextRetryAt: $nextRetryAt,
        );
    }
}
