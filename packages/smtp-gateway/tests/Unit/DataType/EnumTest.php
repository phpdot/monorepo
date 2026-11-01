<?php

declare(strict_types=1);

namespace PHPdot\Mail\Gateway\Tests\Unit\DataType;

use PHPdot\Mail\Gateway\DataType\Enum\AuthMechanism;
use PHPdot\Mail\Gateway\DataType\Enum\QueueState;
use PHPdot\Mail\Gateway\DataType\Enum\ReplyCode;
use PHPdot\Mail\Gateway\DataType\Enum\SessionState;
use PHPdot\Mail\Gateway\DataType\Enum\SmtpCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EnumTest extends TestCase
{
    // === AuthMechanism ===

    #[Test]
    public function authMechanismFromStringValid(): void
    {
        self::assertSame(AuthMechanism::Plain, AuthMechanism::fromString('PLAIN'));
        self::assertSame(AuthMechanism::Login, AuthMechanism::fromString('LOGIN'));
        self::assertSame(AuthMechanism::XOAuth2, AuthMechanism::fromString('XOAUTH2'));
    }

    #[Test]
    public function authMechanismFromStringCaseInsensitive(): void
    {
        self::assertSame(AuthMechanism::Plain, AuthMechanism::fromString('plain'));
        self::assertSame(AuthMechanism::Login, AuthMechanism::fromString('login'));
    }

    #[Test]
    public function authMechanismFromStringReturnsNullForUnknown(): void
    {
        self::assertNull(AuthMechanism::fromString('DIGEST-MD5'));
        self::assertNull(AuthMechanism::fromString(''));
    }

    // === QueueState ===

    #[Test]
    public function queueStateValues(): void
    {
        self::assertSame('pending', QueueState::Pending->value);
        self::assertSame('sending', QueueState::Sending->value);
        self::assertSame('sent', QueueState::Sent->value);
        self::assertSame('failed', QueueState::Failed->value);
    }

    #[Test]
    public function queueStateFromString(): void
    {
        self::assertSame(QueueState::Pending, QueueState::from('pending'));
        self::assertSame(QueueState::Sent, QueueState::from('sent'));
    }

    // === SessionState ===

    #[Test]
    public function sessionStateValues(): void
    {
        self::assertSame('CONNECTED', SessionState::Connected->value);
        self::assertSame('GREETED', SessionState::Greeted->value);
        self::assertSame('SECURED', SessionState::Secured->value);
        self::assertSame('AUTHENTICATED', SessionState::Authenticated->value);
        self::assertSame('TRANSACTION', SessionState::Transaction->value);
        self::assertSame('DATA', SessionState::Data->value);
        self::assertSame('CLOSING', SessionState::Closing->value);
    }

    // === SmtpCommand ===

    #[Test]
    public function smtpCommandValues(): void
    {
        self::assertSame('EHLO', SmtpCommand::Ehlo->value);
        self::assertSame('HELO', SmtpCommand::Helo->value);
        self::assertSame('MAIL', SmtpCommand::MailFrom->value);
        self::assertSame('RCPT', SmtpCommand::RcptTo->value);
        self::assertSame('DATA', SmtpCommand::Data->value);
        self::assertSame('RSET', SmtpCommand::Rset->value);
        self::assertSame('NOOP', SmtpCommand::Noop->value);
        self::assertSame('QUIT', SmtpCommand::Quit->value);
        self::assertSame('STARTTLS', SmtpCommand::StartTls->value);
        self::assertSame('AUTH', SmtpCommand::Auth->value);
    }

    #[Test]
    public function smtpCommandFromLineValid(): void
    {
        self::assertSame(SmtpCommand::Ehlo, SmtpCommand::fromLine('EHLO example.com'));
        self::assertSame(SmtpCommand::MailFrom, SmtpCommand::fromLine('MAIL FROM:<a@b.com>'));
        self::assertSame(SmtpCommand::Quit, SmtpCommand::fromLine('QUIT'));
    }

    #[Test]
    public function smtpCommandFromLineCaseInsensitive(): void
    {
        self::assertSame(SmtpCommand::Ehlo, SmtpCommand::fromLine('ehlo example.com'));
    }

    #[Test]
    public function smtpCommandFromLineReturnsNullForUnknown(): void
    {
        self::assertNull(SmtpCommand::fromLine('VRFY user@test.com'));
    }

    // === ReplyCode ===

    #[Test]
    public function replyCodeSuccessCodes(): void
    {
        self::assertTrue(ReplyCode::ServiceReady->isSuccess());
        self::assertTrue(ReplyCode::Ok->isSuccess());
        self::assertTrue(ReplyCode::AuthSuccess->isSuccess());
    }

    #[Test]
    public function replyCodeIntermediateCodes(): void
    {
        self::assertTrue(ReplyCode::AuthContinue->isIntermediate());
        self::assertTrue(ReplyCode::StartMailInput->isIntermediate());
        self::assertFalse(ReplyCode::Ok->isIntermediate());
    }

    #[Test]
    public function replyCodeTransientFailureCodes(): void
    {
        self::assertTrue(ReplyCode::ServiceNotAvailable->isTransientFailure());
        self::assertTrue(ReplyCode::MailboxBusy->isTransientFailure());
        self::assertFalse(ReplyCode::Ok->isTransientFailure());
    }

    #[Test]
    public function replyCodePermanentFailureCodes(): void
    {
        self::assertTrue(ReplyCode::SyntaxError->isPermanentFailure());
        self::assertTrue(ReplyCode::AuthFailed->isPermanentFailure());
        self::assertTrue(ReplyCode::TransactionFailed->isPermanentFailure());
        self::assertFalse(ReplyCode::Ok->isPermanentFailure());
    }

    #[Test]
    public function replyCodeNoDuplicateValues(): void
    {
        $values = [];
        foreach (ReplyCode::cases() as $case) {
            self::assertNotContains($case->value, $values, "Duplicate reply code: {$case->value}");
            $values[] = $case->value;
        }
    }
}
