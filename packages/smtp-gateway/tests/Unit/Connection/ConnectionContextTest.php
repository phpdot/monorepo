<?php

declare(strict_types=1);

namespace PHPdot\Mail\Gateway\Tests\Unit\Connection;

use PHPdot\Mail\Gateway\Connection\ConnectionContext;
use PHPdot\Mail\Gateway\DataType\Enum\SessionState;
use PHPdot\Mail\Gateway\Protocol\StateMachine;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConnectionContextTest extends TestCase
{
    private ConnectionContext $context;
    private StateMachine $stateMachine;

    protected function setUp(): void
    {
        $this->stateMachine = new StateMachine();
        $this->context = new ConnectionContext(
            clientIp: '192.168.1.100',
            connectionId: 'conn-abc123',
            stateMachine: $this->stateMachine,
        );
    }

    #[Test]
    public function hasReadonlyProperties(): void
    {
        self::assertSame('192.168.1.100', $this->context->clientIp);
        self::assertSame('conn-abc123', $this->context->connectionId);
    }

    #[Test]
    public function startsUnauthenticated(): void
    {
        self::assertFalse($this->context->isAuthenticated());
        self::assertNull($this->context->user());
    }

    #[Test]
    public function setUserAuthenticates(): void
    {
        $this->context->setUser('omar');
        self::assertTrue($this->context->isAuthenticated());
        self::assertSame('omar', $this->context->user());
    }

    #[Test]
    public function clientHostnameStartsNull(): void
    {
        self::assertNull($this->context->clientHostname());
    }

    #[Test]
    public function setClientHostname(): void
    {
        $this->context->setClientHostname('client.example.com');
        self::assertSame('client.example.com', $this->context->clientHostname());
    }

    #[Test]
    public function transactionStartsEmpty(): void
    {
        self::assertNull($this->context->sender());
        self::assertSame([], $this->context->recipients());
        self::assertSame(0, $this->context->recipientCount());
        self::assertFalse($this->context->hasRecipients());
    }

    #[Test]
    public function setSender(): void
    {
        $this->context->setSender('sender@test.com');
        self::assertSame('sender@test.com', $this->context->sender());
    }

    #[Test]
    public function addRecipients(): void
    {
        $this->context->addRecipient('a@test.com');
        $this->context->addRecipient('b@test.com');

        self::assertSame(['a@test.com', 'b@test.com'], $this->context->recipients());
        self::assertSame(2, $this->context->recipientCount());
        self::assertTrue($this->context->hasRecipients());
    }

    #[Test]
    public function resetTransactionClearsSenderAndRecipients(): void
    {
        $this->context->setSender('sender@test.com');
        $this->context->addRecipient('rcpt@test.com');

        $this->context->resetTransaction();

        self::assertNull($this->context->sender());
        self::assertSame([], $this->context->recipients());
        self::assertFalse($this->context->hasRecipients());
    }

    #[Test]
    public function messageCountStartsAtZero(): void
    {
        self::assertSame(0, $this->context->messageCount());
    }

    #[Test]
    public function incrementMessageCount(): void
    {
        $this->context->incrementMessageCount();
        $this->context->incrementMessageCount();
        self::assertSame(2, $this->context->messageCount());
    }

    #[Test]
    public function stateDelegatesToStateMachine(): void
    {
        self::assertSame(SessionState::Connected, $this->context->state());

        $this->stateMachine->greeted();
        self::assertSame(SessionState::Greeted, $this->context->state());
    }

    #[Test]
    public function stateMachineAccessor(): void
    {
        self::assertSame($this->stateMachine, $this->context->stateMachine());
    }
}
