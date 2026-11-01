<?php

declare(strict_types=1);

/**
 * Per-connection session context for SMTP server.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Connection;

use PHPdot\Mail\Gateway\DataType\Enum\SessionState;
use PHPdot\Mail\Gateway\Protocol\StateMachine;

/**
 * Holds per-connection state: auth user, transaction envelope, counters.
 */
final class ConnectionContext
{
    private string|null $user = null;
    private string|null $clientHostname = null;
    private string|null $sender = null;
    /** @var list<string> */
    private array $recipients = [];
    private int $messageCount = 0;

    public function __construct(
        public readonly string $clientIp,
        public readonly string $connectionId,
        private readonly StateMachine $stateMachine,
    ) {}

    public function setUser(string $user): void
    {
        $this->user = $user;
    }

    public function user(): string|null
    {
        return $this->user;
    }

    public function isAuthenticated(): bool
    {
        return $this->user !== null;
    }

    public function setClientHostname(string $hostname): void
    {
        $this->clientHostname = $hostname;
    }

    public function clientHostname(): string|null
    {
        return $this->clientHostname;
    }

    public function setSender(string $sender): void
    {
        $this->sender = $sender;
    }

    public function sender(): string|null
    {
        return $this->sender;
    }

    public function addRecipient(string $recipient): void
    {
        $this->recipients[] = $recipient;
    }

    /**
     * @return list<string>
     */
    public function recipients(): array
    {
        return $this->recipients;
    }

    public function recipientCount(): int
    {
        return count($this->recipients);
    }

    public function hasRecipients(): bool
    {
        return $this->recipients !== [];
    }

    /**
     * Reset mail transaction state (RSET or after DATA completes).
     */
    public function resetTransaction(): void
    {
        $this->sender = null;
        $this->recipients = [];
    }

    public function incrementMessageCount(): void
    {
        $this->messageCount++;
    }

    public function messageCount(): int
    {
        return $this->messageCount;
    }

    public function state(): SessionState
    {
        return $this->stateMachine->state();
    }

    public function stateMachine(): StateMachine
    {
        return $this->stateMachine;
    }
}
