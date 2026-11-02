<?php

declare(strict_types=1);

namespace PHPdot\Mail\Gateway\Tests\Integration;

use PHPdot\Mail\Gateway\DataType\Enum\AuthMechanism;
use PHPdot\Mail\Gateway\DataType\Enum\SessionState;
use PHPdot\Mail\Gateway\Server\Event\AuthEvent;
use PHPdot\Mail\Gateway\Server\Event\MailFromEvent;
use PHPdot\Mail\Gateway\Server\Event\MessageEvent;
use PHPdot\Mail\Gateway\Server\Event\RcptToEvent;
use PHPdot\Mail\Gateway\Server\ServerProtocol;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ServerProtocolTest extends TestCase
{
    private ServerProtocol $server;
    /** @var list<string> */
    private array $receivedMessages;

    protected function setUp(): void
    {
        $this->receivedMessages = [];

        $this->server = new ServerProtocol(
            hostname: 'mail.test.com',
            maxMessageSize: 1048576,
            maxRecipients: 10,
            maxBadCommands: 5,
            tlsRequired: false,
            authRequired: true,
        );

        $this->server->on(AuthEvent::class, function (AuthEvent $e): void {
            if ($e->username() === 'omar' && $e->password() === 'secret') {
                $e->accept();
            } else {
                $e->reject('Invalid credentials');
            }
        });

        $this->server->on(MailFromEvent::class, function (MailFromEvent $e): void {
            $e->accept();
        });

        $this->server->on(RcptToEvent::class, function (RcptToEvent $e): void {
            $e->accept();
        });

        $this->server->on(MessageEvent::class, function (MessageEvent $e): void {
            $this->receivedMessages[] = $e->message;
            $e->accept();
        });
    }

    #[Test]
    public function greeting(): void
    {
        $greeting = $this->server->greeting();
        self::assertStringStartsWith('220 ', $greeting);
        self::assertStringContainsString('mail.test.com', $greeting);
    }

    #[Test]
    public function onlyOfferedAuthMechanismsAreAdvertisedAndAccepted(): void
    {
        $server = new ServerProtocol(
            hostname: 'mail.test.com',
            tlsRequired: false,
            authMechanisms: [AuthMechanism::Plain, AuthMechanism::Login],
        );
        $asked = 0;
        $server->on(AuthEvent::class, function (AuthEvent $e) use (&$asked): void {
            $asked++;
            $e->accept();
        });

        $ehlo = implode('', $server->onData("EHLO client.example.com\r\n"));
        self::assertStringContainsString("AUTH PLAIN LOGIN\r\n", $ehlo);
        self::assertStringNotContainsString('XOAUTH2', $ehlo);

        $token = base64_encode("user=a@b\x01auth=Bearer t\x01\x01");
        $reply = implode('', $server->onData("AUTH XOAUTH2 {$token}\r\n"));
        self::assertStringStartsWith('504', $reply);
        self::assertSame(0, $asked, 'the host is never asked about a mechanism it does not offer');
    }

    #[Test]
    public function ehloReturnsCapabilities(): void
    {
        $responses = $this->server->onData("EHLO client.example.com\r\n");
        self::assertCount(1, $responses);
        $reply = $responses[0];

        self::assertStringContainsString('250', $reply);
        self::assertStringContainsString('AUTH PLAIN LOGIN XOAUTH2', $reply);
        self::assertStringContainsString('SIZE', $reply);
        self::assertStringContainsString('8BITMIME', $reply);
        self::assertStringContainsString('PIPELINING', $reply);
    }

    #[Test]
    public function authPlainInline(): void
    {
        $this->server->onData("EHLO test\r\n");

        $token = base64_encode("\0omar\0secret");
        $responses = $this->server->onData("AUTH PLAIN $token\r\n");

        self::assertCount(1, $responses);
        self::assertStringStartsWith('235 ', $responses[0]);
        self::assertTrue($this->server->isAuthenticated());
    }

    #[Test]
    public function authPlainFails(): void
    {
        $this->server->onData("EHLO test\r\n");

        $token = base64_encode("\0omar\0wrong");
        $responses = $this->server->onData("AUTH PLAIN $token\r\n");

        self::assertCount(1, $responses);
        self::assertStringStartsWith('535 ', $responses[0]);
        self::assertFalse($this->server->isAuthenticated());
    }

    #[Test]
    public function authLoginMultiStep(): void
    {
        $this->server->onData("EHLO test\r\n");

        // AUTH LOGIN
        $responses = $this->server->onData("AUTH LOGIN\r\n");
        self::assertCount(1, $responses);
        self::assertStringStartsWith('334 ', $responses[0]); // Username prompt

        // Send username
        $responses = $this->server->onData(base64_encode('omar') . "\r\n");
        self::assertCount(1, $responses);
        self::assertStringStartsWith('334 ', $responses[0]); // Password prompt

        // Send password
        $responses = $this->server->onData(base64_encode('secret') . "\r\n");
        self::assertCount(1, $responses);
        self::assertStringStartsWith('235 ', $responses[0]);
        self::assertTrue($this->server->isAuthenticated());
    }

    #[Test]
    public function authXOAuth2(): void
    {
        // Create a fresh server with XOAUTH2-specific auth handler
        $server = new ServerProtocol(
            hostname: 'mail.test.com',
            tlsRequired: false,
            authRequired: true,
        );

        $server->on(AuthEvent::class, function (AuthEvent $e): void {
            // Accept any XOAUTH2
            $e->accept();
        });

        $server->onData("EHLO test\r\n");

        $token = base64_encode("user=omar@gmail.com\x01auth=Bearer ya29.token\x01\x01");
        $responses = $server->onData("AUTH XOAUTH2 $token\r\n");

        self::assertCount(1, $responses);
        self::assertStringStartsWith('235 ', $responses[0]);
    }

    #[Test]
    public function mailFromBeforeAuthRejected(): void
    {
        $this->server->onData("EHLO test\r\n");
        $responses = $this->server->onData("MAIL FROM:<omar@test.com>\r\n");

        self::assertStringStartsWith('530 ', $responses[0]);
    }

    #[Test]
    public function fullMessageTransaction(): void
    {
        // EHLO + AUTH
        $this->server->onData("EHLO test\r\n");
        $token = base64_encode("\0omar\0secret");
        $this->server->onData("AUTH PLAIN $token\r\n");

        // MAIL FROM
        $responses = $this->server->onData("MAIL FROM:<omar@test.com>\r\n");
        self::assertStringStartsWith('250 ', $responses[0]);

        // RCPT TO
        $responses = $this->server->onData("RCPT TO:<bob@example.com>\r\n");
        self::assertStringStartsWith('250 ', $responses[0]);

        // DATA
        $responses = $this->server->onData("DATA\r\n");
        self::assertStringStartsWith('354 ', $responses[0]);

        // Send message body
        $responses = $this->server->onData(
            "From: omar@test.com\r\n"
            . "To: bob@example.com\r\n"
            . "Subject: Hello\r\n"
            . "\r\n"
            . "Hi Bob!\r\n"
            . ".\r\n",
        );

        self::assertCount(1, $responses);
        self::assertStringStartsWith('250 ', $responses[0]);
        self::assertStringContainsString('queued', $responses[0]);

        // Verify message was received
        self::assertCount(1, $this->receivedMessages);
        self::assertStringContainsString('Subject: Hello', $this->receivedMessages[0]);
        self::assertStringContainsString('Hi Bob!', $this->receivedMessages[0]);
    }

    #[Test]
    public function dotUnstuffing(): void
    {
        $this->authenticateAndStartTransaction();

        $this->server->onData("DATA\r\n");
        $responses = $this->server->onData(
            "Subject: Test\r\n"
            . "\r\n"
            . "..This line starts with a dot\r\n"
            . ".Another dot-stuffed line\r\n"
            . "Normal line\r\n"
            . ".\r\n",
        );

        self::assertStringStartsWith('250 ', $responses[0]);
        self::assertStringContainsString('.This line starts with a dot', $this->receivedMessages[0]);
        self::assertStringNotContainsString('..This line', $this->receivedMessages[0]);
    }

    #[Test]
    public function multipleRecipients(): void
    {
        $this->authenticateAndStartTransaction();

        $this->server->onData("RCPT TO:<alice@example.com>\r\n");
        $responses = $this->server->onData("RCPT TO:<charlie@example.com>\r\n");
        self::assertStringStartsWith('250 ', $responses[0]);
    }

    #[Test]
    public function rsetClearsTransaction(): void
    {
        $this->authenticateAndStartTransaction();

        $responses = $this->server->onData("RSET\r\n");
        self::assertStringStartsWith('250 ', $responses[0]);

        // RCPT TO should now fail (no MAIL FROM)
        $responses = $this->server->onData("RCPT TO:<bob@test.com>\r\n");
        self::assertStringStartsWith('503 ', $responses[0]);
    }

    #[Test]
    public function noopAlwaysReturnsOk(): void
    {
        $responses = $this->server->onData("NOOP\r\n");
        self::assertStringStartsWith('250 ', $responses[0]);
    }

    #[Test]
    public function quitReturnsBye(): void
    {
        $responses = $this->server->onData("QUIT\r\n");
        self::assertStringStartsWith('221 ', $responses[0]);
    }

    #[Test]
    public function unknownCommandReturnsSyntaxError(): void
    {
        $responses = $this->server->onData("VRFY user@test.com\r\n");
        self::assertStringStartsWith('500 ', $responses[0]);
    }

    #[Test]
    public function tooManyBadCommandsDisconnects(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->server->onData("BADCMD\r\n");
        }

        $responses = $this->server->onData("BADCMD\r\n");
        self::assertStringStartsWith('421 ', $responses[0]);
    }

    #[Test]
    public function pipelining(): void
    {
        $this->server->onData("EHLO test\r\n");
        $token = base64_encode("\0omar\0secret");
        $this->server->onData("AUTH PLAIN $token\r\n");

        // Pipelined commands
        $responses = $this->server->onData(
            "MAIL FROM:<omar@test.com>\r\n"
            . "RCPT TO:<bob@example.com>\r\n"
            . "DATA\r\n",
        );

        self::assertCount(3, $responses);
        self::assertStringStartsWith('250 ', $responses[0]); // MAIL FROM
        self::assertStringStartsWith('250 ', $responses[1]); // RCPT TO
        self::assertStringStartsWith('354 ', $responses[2]); // DATA
    }

    #[Test]
    public function messageSizeEnforced(): void
    {
        $this->authenticateAndStartTransaction();

        // Declare a size that exceeds the limit
        $responses = $this->server->onData("RSET\r\n");
        $responses = $this->server->onData("MAIL FROM:<omar@test.com> SIZE=2000000\r\n");
        self::assertStringStartsWith('552 ', $responses[0]);
    }

    #[Test]
    public function multipleMessagesInOneSession(): void
    {
        // First message
        $this->authenticateAndStartTransaction();
        $this->server->onData("DATA\r\n");
        $this->server->onData("Subject: First\r\n\r\nFirst message\r\n.\r\n");

        // Second message (no re-auth needed)
        $this->server->onData("MAIL FROM:<omar@test.com>\r\n");
        $this->server->onData("RCPT TO:<alice@example.com>\r\n");
        $this->server->onData("DATA\r\n");
        $this->server->onData("Subject: Second\r\n\r\nSecond message\r\n.\r\n");

        self::assertCount(2, $this->receivedMessages);
    }

    #[Test]
    public function heloFallback(): void
    {
        $responses = $this->server->onData("HELO test\r\n");
        self::assertCount(1, $responses);
        self::assertStringStartsWith('250 ', $responses[0]);
    }

    #[Test]
    public function ehloWithoutHostnameRejected(): void
    {
        $responses = $this->server->onData("EHLO\r\n");
        self::assertStringStartsWith('501 ', $responses[0]);
    }

    #[Test]
    public function dataWithNoRecipientsRejected(): void
    {
        $this->server->onData("EHLO test\r\n");
        $token = base64_encode("\0omar\0secret");
        $this->server->onData("AUTH PLAIN $token\r\n");
        $this->server->onData("MAIL FROM:<omar@test.com>\r\n");

        // DATA without RCPT TO
        $responses = $this->server->onData("DATA\r\n");
        self::assertStringStartsWith('503 ', $responses[0]);
    }

    #[Test]
    public function authNotAdvertisedBeforeTlsWhenRequired(): void
    {
        $server = new ServerProtocol(
            hostname: 'secure.test.com',
            tlsRequired: true,
            authRequired: true,
        );

        $responses = $server->onData("EHLO test\r\n");
        $reply = $responses[0];

        // Should advertise STARTTLS but NOT AUTH before TLS
        self::assertStringContainsString('STARTTLS', $reply);
        self::assertStringNotContainsString('AUTH', $reply);
    }

    #[Test]
    public function authAdvertisedAfterTlsUpgrade(): void
    {
        $server = new ServerProtocol(
            hostname: 'secure.test.com',
            tlsRequired: true,
            authRequired: true,
        );

        $server->on(AuthEvent::class, function (AuthEvent $e): void {
            $e->accept();
        });

        $server->onData("EHLO test\r\n");
        $server->onData("STARTTLS\r\n");
        $server->tlsUpgraded();

        // Re-EHLO after TLS
        $responses = $server->onData("EHLO test\r\n");
        $reply = $responses[0];

        self::assertStringContainsString('AUTH PLAIN LOGIN XOAUTH2', $reply);
        self::assertStringNotContainsString('STARTTLS', $reply);
    }

    #[Test]
    public function authNotAdvertisedAfterAuthentication(): void
    {
        $this->server->onData("EHLO test\r\n");
        $token = base64_encode("\0omar\0secret");
        $this->server->onData("AUTH PLAIN $token\r\n");

        // Re-EHLO after auth
        $responses = $this->server->onData("EHLO test\r\n");
        $reply = $responses[0];

        self::assertStringNotContainsString('AUTH', $reply);
    }

    #[Test]
    public function authLoginWithInitialUsername(): void
    {
        $this->server->onData("EHLO test\r\n");

        // AUTH LOGIN with initial username
        $responses = $this->server->onData("AUTH LOGIN " . base64_encode('omar') . "\r\n");
        self::assertStringStartsWith('334 ', $responses[0]); // Password prompt

        // Send password
        $responses = $this->server->onData(base64_encode('secret') . "\r\n");
        self::assertStringStartsWith('235 ', $responses[0]);
    }

    #[Test]
    public function authCancelledWithAsterisk(): void
    {
        $this->server->onData("EHLO test\r\n");

        // AUTH PLAIN with cancel — RFC 4954 §4 requires a 501 reply
        $responses = $this->server->onData("AUTH PLAIN *\r\n");
        self::assertStringStartsWith('501 ', $responses[0]);
    }

    #[Test]
    public function authLoginCancelDuringUsername(): void
    {
        $this->server->onData("EHLO test\r\n");
        $this->server->onData("AUTH LOGIN\r\n");

        // RFC 4954 §4: cancel (*) must be rejected with 501
        $responses = $this->server->onData("*\r\n");
        self::assertStringStartsWith('501 ', $responses[0]);
    }

    #[Test]
    public function authLoginCancelDuringPassword(): void
    {
        $this->server->onData("EHLO test\r\n");
        $this->server->onData("AUTH LOGIN\r\n");
        $this->server->onData(base64_encode('omar') . "\r\n");

        // RFC 4954 §4: cancel (*) must be rejected with 501
        $responses = $this->server->onData("*\r\n");
        self::assertStringStartsWith('501 ', $responses[0]);
    }

    #[Test]
    public function unsupportedAuthMechanismRejected(): void
    {
        $this->server->onData("EHLO test\r\n");

        $responses = $this->server->onData("AUTH DIGEST-MD5\r\n");
        self::assertStringStartsWith('504 ', $responses[0]);
    }

    #[Test]
    public function doubleAuthRejected(): void
    {
        $this->server->onData("EHLO test\r\n");
        $token = base64_encode("\0omar\0secret");
        $this->server->onData("AUTH PLAIN $token\r\n");

        $responses = $this->server->onData("AUTH PLAIN $token\r\n");
        self::assertStringStartsWith('503 ', $responses[0]);
    }

    #[Test]
    public function messageSizeEnforcedDuringDataTransmission(): void
    {
        $server = new ServerProtocol(
            hostname: 'test',
            maxMessageSize: 100,
            tlsRequired: false,
            authRequired: false,
        );

        $server->on(MailFromEvent::class, fn(MailFromEvent $e) => $e->accept());
        $server->on(RcptToEvent::class, fn(RcptToEvent $e) => $e->accept());
        $server->on(MessageEvent::class, fn(MessageEvent $e) => $e->accept());

        $server->onData("EHLO test\r\n");
        $server->onData("MAIL FROM:<a@b.com>\r\n");
        $server->onData("RCPT TO:<c@d.com>\r\n");
        $server->onData("DATA\r\n");

        self::assertSame([], $server->onData(str_repeat('A', 120) . "\r\n"));
        self::assertSame([], $server->onData("RCPT TO:<attacker@evil.test>\r\n"));

        $responses = $server->onData(".\r\n");
        self::assertCount(1, $responses);
        self::assertStringStartsWith('552 ', $responses[0]);
    }

    #[Test]
    public function emptyRecipientAddressRejected(): void
    {
        $this->authenticateAndStartTransaction();

        // Reset and start new transaction
        $this->server->onData("RSET\r\n");
        $this->server->onData("MAIL FROM:<omar@test.com>\r\n");
        $responses = $this->server->onData("RCPT TO:<>\r\n");
        self::assertStringStartsWith('501 ', $responses[0]);
    }

    #[Test]
    public function recipientLimitEnforced(): void
    {
        $server = new ServerProtocol(
            hostname: 'test',
            maxRecipients: 2,
            tlsRequired: false,
            authRequired: false,
        );

        $server->on(MailFromEvent::class, fn(MailFromEvent $e) => $e->accept());
        $server->on(RcptToEvent::class, fn(RcptToEvent $e) => $e->accept());

        $server->onData("EHLO test\r\n");
        $server->onData("MAIL FROM:<a@b.com>\r\n");
        $server->onData("RCPT TO:<one@test.com>\r\n");
        $server->onData("RCPT TO:<two@test.com>\r\n");

        $responses = $server->onData("RCPT TO:<three@test.com>\r\n");
        self::assertStringStartsWith('452 ', $responses[0]);
    }

    #[Test]
    public function lastEventIsTracked(): void
    {
        $this->server->onData("EHLO test\r\n");
        $token = base64_encode("\0omar\0secret");
        $this->server->onData("AUTH PLAIN $token\r\n");

        self::assertNotNull($this->server->lastEvent());
        self::assertInstanceOf(\PHPdot\Mail\Gateway\Server\Event\AuthEvent::class, $this->server->lastEvent());
    }

    #[Test]
    public function customCapabilitiesAdvertised(): void
    {
        $this->server->addCapability('CHUNKING');
        $responses = $this->server->onData("EHLO test\r\n");

        self::assertStringContainsString('CHUNKING', $responses[0]);
    }

    #[Test]
    public function startTlsDiscardsCommandsPipelinedBehindIt(): void
    {
        $server = $this->tlsServer();
        $server->onData("EHLO test\r\n");

        $token = base64_encode("\0omar\0secret");
        self::assertSame(["220 Ready to start TLS\r\n"], $server->onData("STARTTLS\r\nAUTH PLAIN $token\r\n"));
        self::assertTrue($server->needsTlsUpgrade());
        self::assertFalse($server->isAuthenticated());
        self::assertSame([], $server->onData("MAIL FROM:<a@b.test>\r\n"));

        $server->tlsUpgraded();
        $server->onData("EHLO test\r\n");
        self::assertStringStartsWith('235 ', $server->onData("AUTH PLAIN $token\r\n")[0]);
    }

    #[Test]
    public function tlsUpgradeForgetsAnAuthLoginStartedBeforeIt(): void
    {
        $server = $this->tlsServer();
        $server->onData("EHLO test\r\n");
        $server->onData("STARTTLS\r\n");
        $server->tlsUpgraded();

        self::assertStringStartsWith('250-', $server->onData("EHLO client.example\r\n")[0]);
    }

    #[Test]
    public function oversizeMessageIsReadToItsDotBeforeItIsRefused(): void
    {
        $server = new ServerProtocol(hostname: 'test', maxMessageSize: 100, tlsRequired: false, authRequired: false);
        $delivered = [];
        $server->on(MessageEvent::class, function (MessageEvent $e) use (&$delivered): void {
            $delivered[] = $e->recipients;
            $e->accept();
        });

        $server->onData("EHLO test\r\nMAIL FROM:<a@b.test>\r\nRCPT TO:<c@d.test>\r\nDATA\r\n");
        $responses = $server->onData(
            str_repeat('X', 120) . "\r\nMAIL FROM:<a@b.test>\r\nRCPT TO:<attacker@evil.test>\r\nDATA\r\nsmuggled\r\n.\r\n",
        );

        self::assertCount(1, $responses);
        self::assertStringStartsWith('552 ', $responses[0]);
        self::assertSame([], $delivered);
    }

    #[Test]
    public function bareLineFeedNeverEndsTheMessage(): void
    {
        $this->authenticateAndStartTransaction();
        $this->server->onData("DATA\r\n");

        $responses = $this->server->onData(
            "Subject: a\r\n\r\nhello\n.\nMAIL FROM:<a@b.test>\r\nRCPT TO:<hidden@evil.test>\r\nDATA\r\nx\r\n.\r\n",
        );

        self::assertCount(1, $responses);
        self::assertCount(1, $this->receivedMessages);
        self::assertSame(
            "Subject: a\r\n\r\nhello\r\n.\r\nMAIL FROM:<a@b.test>\r\nRCPT TO:<hidden@evil.test>\r\nDATA\r\nx\r\n",
            $this->receivedMessages[0],
        );
    }

    #[Test]
    public function commandWithBareLineFeedIsRefused(): void
    {
        $this->authenticateAndStartTransaction();

        $responses = $this->server->onData("RCPT TO:<bob@y.test\nRCPT TO:<hidden@evil.test>>\r\n");

        self::assertStringStartsWith('500 ', $responses[0]);
    }

    #[Test]
    public function malformedEnvelopeAddressesAreRefused(): void
    {
        $this->server->onData("EHLO test\r\n");
        $this->server->onData('AUTH PLAIN ' . base64_encode("\0omar\0secret") . "\r\n");

        self::assertStringStartsWith('501 ', $this->server->onData("MAIL FROM:<a b c>\r\n")[0]);
        self::assertStringStartsWith('501 ', $this->server->onData("MAIL FROM:a@b.test\r\n")[0]);
        self::assertStringStartsWith('250 ', $this->server->onData("MAIL FROM:<>\r\n")[0]);
        self::assertStringStartsWith('501 ', $this->server->onData("RCPT TO:junk no brackets\r\n")[0]);
        self::assertStringStartsWith('501 ', $this->server->onData("RCPT TO:<nobody>\r\n")[0]);
        self::assertStringStartsWith('250 ', $this->server->onData("RCPT TO:<Postmaster>\r\n")[0]);
    }

    #[Test]
    public function badCommandLimitClosesTheSession(): void
    {
        $responses = $this->server->onData(str_repeat("BOGUS\r\n", 8));

        self::assertCount(5, $responses);
        self::assertStringStartsWith('421 ', $responses[4]);
        self::assertSame(SessionState::Closing, $this->server->state());
        self::assertSame([], $this->server->onData("NOOP\r\n"));
    }

    #[Test]
    public function failedAuthenticationCountsTowardTheLimit(): void
    {
        $this->server->onData("EHLO test\r\n");
        $wrong = 'AUTH PLAIN ' . base64_encode("\0omar\0wrong") . "\r\n";

        $responses = $this->server->onData(str_repeat($wrong, 10));

        self::assertCount(5, $responses);
        self::assertStringStartsWith('421 ', $responses[4]);
    }

    #[Test]
    public function lineFeedFloodBeforeLoginIsOneReply(): void
    {
        $started = microtime(true);
        $responses = $this->server->onData(str_repeat("\n", 1_048_576));

        self::assertCount(1, $responses);
        self::assertStringStartsWith('500 ', $responses[0]);
        self::assertLessThan(1.0, microtime(true) - $started);
    }

    #[Test]
    public function overlongCommandIsRefusedWholeAcrossReads(): void
    {
        $this->server->onData("EHLO test\r\n");

        self::assertSame([], $this->server->onData('NOOP ' . str_repeat('A', 600)));
        $responses = $this->server->onData("RSET\r\n");

        self::assertCount(1, $responses);
        self::assertStringStartsWith('500 ', $responses[0]);
    }

    #[Test]
    public function longAuthLineIsAccepted(): void
    {
        $server = new ServerProtocol(hostname: 'test', tlsRequired: false);
        $server->on(AuthEvent::class, fn(AuthEvent $e) => $e->accept());
        $server->onData("EHLO test\r\n");
        $token = base64_encode("user=omar\x01auth=Bearer " . str_repeat('t', 900) . "\x01\x01");

        self::assertGreaterThan(510, strlen("AUTH XOAUTH2 $token"));
        self::assertStringStartsWith('235 ', $server->onData("AUTH XOAUTH2 $token\r\n")[0]);
    }

    #[Test]
    public function authPlainWithoutInitialResponseTakesTheNextLine(): void
    {
        $this->server->onData("EHLO test\r\n");

        self::assertSame(["334 \r\n"], $this->server->onData("AUTH PLAIN\r\n"));
        self::assertStringStartsWith('235 ', $this->server->onData(base64_encode("\0omar\0secret") . "\r\n")[0]);
    }

    #[Test]
    public function xoauth2WithoutInitialResponseTakesTheNextLine(): void
    {
        $server = new ServerProtocol(hostname: 'test', tlsRequired: false);
        $server->on(AuthEvent::class, fn(AuthEvent $e) => $e->accept());
        $server->onData("EHLO test\r\n");

        self::assertSame(["334 \r\n"], $server->onData("AUTH XOAUTH2\r\n"));
        $token = base64_encode("user=omar\x01auth=Bearer tok\x01\x01");
        self::assertStringStartsWith('235 ', $server->onData($token . "\r\n")[0]);
    }

    #[Test]
    public function ehloAfterAuthenticationKeepsTheSessionSignedIn(): void
    {
        $this->server->onData("EHLO test\r\n");
        $this->server->onData('AUTH PLAIN ' . base64_encode("\0omar\0secret") . "\r\n");

        $ehlo = $this->server->onData("EHLO again\r\n")[0];

        self::assertStringNotContainsString('AUTH', $ehlo);
        self::assertStringStartsWith('250 ', $this->server->onData("MAIL FROM:<omar@test.com>\r\n")[0]);
    }

    #[Test]
    public function enhancedStatusCodesAreNotAdvertised(): void
    {
        self::assertStringNotContainsString('ENHANCEDSTATUSCODES', $this->server->onData("EHLO test\r\n")[0]);
    }

    #[Test]
    public function clientTextCannotForgeAReplyLine(): void
    {
        $server = new ServerProtocol(hostname: 'test', tlsRequired: false, authRequired: false);
        $server->on(MailFromEvent::class, fn(MailFromEvent $e) => $e->reject("no\r\n250 forged"));
        $server->onData("EHLO test\r\n");

        $reply = $server->onData("MAIL FROM:<a@b.test>\r\n")[0];

        self::assertSame(1, substr_count($reply, "\r\n"));
        self::assertStringEndsWith("no  250 forged\r\n", $reply);
    }

    #[Test]
    public function mailFromInsideATransactionIsBadSequence(): void
    {
        $this->authenticateAndStartTransaction();

        self::assertStringStartsWith('503 ', $this->server->onData("MAIL FROM:<other@test.com>\r\n")[0]);
    }

    #[Test]
    public function messageEventCarriesTheClientAddress(): void
    {
        $server = new ServerProtocol(hostname: 'test', tlsRequired: false, authRequired: false, clientIp: '203.0.113.9');
        $seen = null;
        $server->on(MessageEvent::class, function (MessageEvent $e) use (&$seen): void {
            $seen = $e->clientIp;
            $e->accept();
        });

        $server->onData("EHLO test\r\nMAIL FROM:<a@b.test>\r\nRCPT TO:<c@d.test>\r\nDATA\r\nx\r\n.\r\n");

        self::assertSame('203.0.113.9', $seen);
    }

    private function tlsServer(): ServerProtocol
    {
        $server = new ServerProtocol(hostname: 'mail.test.com', tlsRequired: true);
        $server->on(AuthEvent::class, function (AuthEvent $e): void {
            $e->username() === 'omar' && $e->password() === 'secret' ? $e->accept() : $e->reject('Invalid credentials');
        });

        return $server;
    }

    private function authenticateAndStartTransaction(): void
    {
        $this->server->onData("EHLO test\r\n");
        $token = base64_encode("\0omar\0secret");
        $this->server->onData("AUTH PLAIN $token\r\n");
        $this->server->onData("MAIL FROM:<omar@test.com>\r\n");
        $this->server->onData("RCPT TO:<bob@example.com>\r\n");
    }
}
