<?php

declare(strict_types=1);

namespace PHPdot\Mail\Gateway\Tests\Unit\Protocol;

use PHPdot\Mail\Gateway\DataType\Enum\SessionState;
use PHPdot\Mail\Gateway\DataType\Enum\SmtpCommand;
use PHPdot\Mail\Gateway\Protocol\StateMachine;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StateMachineTest extends TestCase
{
    #[Test]
    public function startsInConnectedState(): void
    {
        $sm = new StateMachine();
        self::assertSame(SessionState::Connected, $sm->state());
    }

    #[Test]
    public function ehloTransitionsToGreeted(): void
    {
        $sm = new StateMachine();
        self::assertTrue($sm->isAllowed(SmtpCommand::Ehlo));
        $sm->greeted();
        self::assertSame(SessionState::Greeted, $sm->state());
    }

    #[Test]
    public function starttlsAllowedAfterEhlo(): void
    {
        $sm = new StateMachine();
        $sm->greeted();
        self::assertTrue($sm->isAllowed(SmtpCommand::StartTls));
    }

    #[Test]
    public function starttlsNotAllowedBeforeEhlo(): void
    {
        $sm = new StateMachine();
        self::assertFalse($sm->isAllowed(SmtpCommand::StartTls));
    }

    #[Test]
    public function authRequiresTlsWhenTlsRequired(): void
    {
        $sm = new StateMachine(tlsRequired: true);
        $sm->greeted();
        self::assertFalse($sm->isAllowed(SmtpCommand::Auth));

        $sm->secured();
        self::assertTrue($sm->isAllowed(SmtpCommand::Auth));
    }

    #[Test]
    public function authAllowedWithoutTlsWhenNotRequired(): void
    {
        $sm = new StateMachine(tlsRequired: false);
        $sm->greeted();
        self::assertTrue($sm->isAllowed(SmtpCommand::Auth));
    }

    #[Test]
    public function mailFromRequiresAuthWhenAuthRequired(): void
    {
        $sm = new StateMachine(tlsRequired: false, authRequired: true);
        $sm->greeted();
        self::assertFalse($sm->isAllowed(SmtpCommand::MailFrom));

        $sm->authenticated();
        self::assertTrue($sm->isAllowed(SmtpCommand::MailFrom));
    }

    #[Test]
    public function mailFromAllowedWithoutAuthWhenNotRequired(): void
    {
        $sm = new StateMachine(tlsRequired: false, authRequired: false);
        $sm->greeted();
        self::assertTrue($sm->isAllowed(SmtpCommand::MailFrom));
    }

    #[Test]
    public function rcptToRequiresTransaction(): void
    {
        $sm = new StateMachine(tlsRequired: false, authRequired: false);
        $sm->greeted();
        self::assertFalse($sm->isAllowed(SmtpCommand::RcptTo));

        $sm->transaction();
        self::assertTrue($sm->isAllowed(SmtpCommand::RcptTo));
    }

    #[Test]
    public function dataRequiresTransaction(): void
    {
        $sm = new StateMachine(tlsRequired: false, authRequired: false);
        $sm->greeted();
        self::assertFalse($sm->isAllowed(SmtpCommand::Data));

        $sm->transaction();
        self::assertTrue($sm->isAllowed(SmtpCommand::Data));
    }

    #[Test]
    public function rsetAlwaysAllowed(): void
    {
        $sm = new StateMachine();
        self::assertTrue($sm->isAllowed(SmtpCommand::Rset));
    }

    #[Test]
    public function noopAlwaysAllowed(): void
    {
        $sm = new StateMachine();
        self::assertTrue($sm->isAllowed(SmtpCommand::Noop));
    }

    #[Test]
    public function quitAlwaysAllowed(): void
    {
        $sm = new StateMachine();
        self::assertTrue($sm->isAllowed(SmtpCommand::Quit));
    }

    #[Test]
    public function resetReturnsToAuthenticated(): void
    {
        $sm = new StateMachine(tlsRequired: false, authRequired: false);
        $sm->greeted();
        $sm->authenticated();
        $sm->transaction();
        $sm->reset();
        self::assertSame(SessionState::Authenticated, $sm->state());
    }

    #[Test]
    public function dataCompleteReturnsToAuthenticated(): void
    {
        $sm = new StateMachine(tlsRequired: false, authRequired: false);
        $sm->greeted();
        $sm->authenticated();
        $sm->transaction();
        $sm->data();
        self::assertSame(SessionState::Data, $sm->state());

        $sm->dataComplete();
        self::assertSame(SessionState::Authenticated, $sm->state());
    }

    #[Test]
    public function closingState(): void
    {
        $sm = new StateMachine();
        $sm->closing();
        self::assertSame(SessionState::Closing, $sm->state());
    }

    #[Test]
    public function fullSessionFlow(): void
    {
        $sm = new StateMachine(tlsRequired: true, authRequired: true);

        // Connected → EHLO → Greeted
        $sm->greeted();
        self::assertSame(SessionState::Greeted, $sm->state());

        // STARTTLS → Secured
        $sm->secured();
        self::assertSame(SessionState::Secured, $sm->state());

        // Re-EHLO after TLS
        $sm->regreeted();
        self::assertSame(SessionState::Secured, $sm->state());

        // AUTH → Authenticated
        $sm->authenticated();
        self::assertSame(SessionState::Authenticated, $sm->state());

        // MAIL FROM → Transaction
        $sm->transaction();
        self::assertSame(SessionState::Transaction, $sm->state());

        // DATA → Data
        $sm->data();
        self::assertSame(SessionState::Data, $sm->state());

        // . → back to Authenticated
        $sm->dataComplete();
        self::assertSame(SessionState::Authenticated, $sm->state());

        // QUIT → Closing
        $sm->closing();
        self::assertSame(SessionState::Closing, $sm->state());
    }

    #[Test]
    public function regreetedFromTransactionReturnsToSecured(): void
    {
        $sm = new StateMachine(tlsRequired: true, authRequired: true);
        $sm->greeted();
        $sm->secured();
        $sm->authenticated();
        $sm->transaction();

        // Client sends EHLO during transaction — TLS still active
        $sm->regreeted();
        self::assertSame(SessionState::Secured, $sm->state());
    }

    #[Test]
    public function regreetedFromDataReturnsToSecured(): void
    {
        $sm = new StateMachine(tlsRequired: true, authRequired: true);
        $sm->greeted();
        $sm->secured();
        $sm->authenticated();
        $sm->transaction();
        $sm->data();

        $sm->regreeted();
        self::assertSame(SessionState::Secured, $sm->state());
    }

    #[Test]
    public function isTlsRequired(): void
    {
        $required = new StateMachine(tlsRequired: true);
        self::assertTrue($required->isTlsRequired());

        $notRequired = new StateMachine(tlsRequired: false);
        self::assertFalse($notRequired->isTlsRequired());
    }
}
