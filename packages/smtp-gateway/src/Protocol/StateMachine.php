<?php

declare(strict_types=1);

/**
 * Manages SMTP session state transitions per RFC 5321.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Protocol;

use PHPdot\Mail\Gateway\DataType\Enum\SessionState;
use PHPdot\Mail\Gateway\DataType\Enum\SmtpCommand;
use PHPdot\Mail\Gateway\Exception\StateException;

/**
 * SMTP session state machine.
 *
 * Enforces valid command ordering per RFC 5321 Section 4.1.4.
 */
final class StateMachine
{
    private SessionState $state;
    private bool $tlsRequired;
    private bool $authRequired;

    public function __construct(
        SessionState $initialState = SessionState::Connected,
        bool $tlsRequired = true,
        bool $authRequired = true,
    ) {
        $this->state = $initialState;
        $this->tlsRequired = $tlsRequired;
        $this->authRequired = $authRequired;
    }

    public function state(): SessionState
    {
        return $this->state;
    }

    /**
     * Check if a command is allowed in the current state.
     */
    public function isAllowed(SmtpCommand $command): bool
    {
        return match ($command) {

            SmtpCommand::Ehlo, SmtpCommand::Helo => $this->state !== SessionState::Data,

            SmtpCommand::StartTls => $this->state === SessionState::Greeted,

            SmtpCommand::Auth => $this->canAuth(),

            SmtpCommand::MailFrom => $this->canStartTransaction(),

            SmtpCommand::RcptTo => $this->state === SessionState::Transaction,

            SmtpCommand::Data => $this->state === SessionState::Transaction,

            SmtpCommand::Rset, SmtpCommand::Noop, SmtpCommand::Quit => true,
        };
    }

    /**
     * @throws StateException
     */
    public function assertAllowed(SmtpCommand $command): void
    {
        if (!$this->isAllowed($command)) {
            throw new StateException(sprintf(
                'Command %s not allowed in %s state',
                $command->value,
                $this->state->value,
            ));
        }
    }

    public function greeted(): void
    {
        $this->state = SessionState::Greeted;
    }

    public function secured(): void
    {
        $this->state = SessionState::Secured;
    }

    public function authenticated(): void
    {
        $this->state = SessionState::Authenticated;
    }

    public function transaction(): void
    {
        $this->state = SessionState::Transaction;
    }

    public function data(): void
    {
        $this->state = SessionState::Data;
    }

    public function dataComplete(): void
    {

        $this->state = SessionState::Authenticated;
    }

    public function reset(): void
    {

        if ($this->state === SessionState::Transaction || $this->state === SessionState::Data) {
            $this->state = SessionState::Authenticated;
        }
    }

    public function closing(): void
    {
        $this->state = SessionState::Closing;
    }

    /**
     * After re-EHLO (post-STARTTLS), reset session but preserve TLS state.
     *
     * Per RFC 5321 Section 4.1.4, EHLO resets all tables and buffers,
     * but the TLS layer remains active.
     */
    public function regreeted(): void
    {

        $this->state = SessionState::Secured;
    }

    public function isAuthenticated(): bool
    {
        return $this->state === SessionState::Authenticated
            || $this->state === SessionState::Transaction
            || $this->state === SessionState::Data;
    }

    public function isSecured(): bool
    {
        return $this->state === SessionState::Secured
            || ($this->isAuthenticated() && $this->tlsRequired);
    }

    public function isTlsRequired(): bool
    {
        return $this->tlsRequired;
    }

    public function isInData(): bool
    {
        return $this->state === SessionState::Data;
    }

    private function canAuth(): bool
    {
        if ($this->isAuthenticated()) {
            return false;
        }

        if ($this->tlsRequired) {
            return $this->state === SessionState::Secured;
        }

        return $this->state === SessionState::Greeted || $this->state === SessionState::Secured;
    }

    private function canStartTransaction(): bool
    {
        if ($this->authRequired) {
            return $this->isAuthenticated();
        }

        return $this->state === SessionState::Greeted
            || $this->state === SessionState::Secured
            || $this->isAuthenticated();
    }
}
