<?php

declare(strict_types=1);

namespace PHPdot\Mail\Tests\Integration;

use PHPdot\Mail\MailConfig;
use PHPdot\Mail\Mailer;
use PHPdot\Mail\Tests\Support\Mailpit;
use PHPdot\Mail\Transport\EmailFactory;
use PHPdot\Mail\Transport\Transport;
use PHPUnit\Framework\TestCase;

/**
 * Real delivery against the compose stack's Mailpit catcher. Every test sends
 * over SMTP and asserts on what Mailpit actually received, which is the only
 * place the wire format — envelope, headers, MIME parts — can be observed.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class MailpitDeliveryTest extends TestCase
{
    use RequiresMailpit;

    private Mailpit $mailpit;

    protected function setUp(): void
    {
        $this->skipUnlessMailpitAvailable();
        $this->mailpit = Mailpit::fromEnv();
        $this->mailpit->purge();
    }

    private function mailer(): Mailer
    {
        return new Mailer(new MailConfig(dsn: self::mailpitSmtpDsn()), new Transport(new EmailFactory()));
    }

    public function testDeliversTheComposedEnvelopeAndBothBodies(): void
    {
        $receipt = $this->mailer()->message()
            ->from('no-reply@example.com', 'App')
            ->to('alice@example.com', 'Alice')
            ->cc('cc@example.com')
            ->replyTo('reply@example.com')
            ->subject('Welcome')
            ->text('Hello plain')
            ->html('<p>Hello html</p>')
            ->send();

        $messages = $this->mailpit->messages();
        self::assertCount(1, $messages);

        $delivered = $this->mailpit->message($receipt->messageId);
        self::assertSame($messages[0]['ID'], $receipt->messageId);
        self::assertSame('App', $delivered['From']['Name']);
        self::assertSame('no-reply@example.com', $delivered['From']['Address']);
        self::assertSame('Alice', $delivered['To'][0]['Name']);
        self::assertSame('alice@example.com', $delivered['To'][0]['Address']);
        self::assertSame('cc@example.com', $delivered['Cc'][0]['Address']);
        self::assertSame('reply@example.com', $delivered['ReplyTo'][0]['Address']);
        self::assertSame('no-reply@example.com', $delivered['ReturnPath']);
        self::assertSame('Welcome', $delivered['Subject']);
        self::assertSame('Hello plain', $delivered['Text']);
        self::assertSame('<p>Hello html</p>', $delivered['HTML']);
    }

    public function testCustomHeadersAndPriorityReachTheWire(): void
    {
        $receipt = $this->mailer()->message()
            ->from('no-reply@example.com')
            ->to('alice@example.com')
            ->subject('Campaign launch')
            ->text('x')
            ->priority(2)
            ->header('X-Campaign', 'welcome')
            ->send();

        $headers = $this->mailpit->headers($receipt->messageId);

        self::assertSame(['welcome'], $headers['X-Campaign']);
        self::assertSame(['2 (High)'], $headers['X-Priority']);
    }

    public function testAttachmentsSurviveTheSendTimeMimeBuild(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'phpdot-mail-att');
        file_put_contents($path, 'attachment body from disk');

        try {
            $receipt = $this->mailer()->message()
                ->from('no-reply@example.com')
                ->to('alice@example.com')
                ->subject('Quarterly report')
                ->text('x')
                ->attach($path, 'notes.txt')
                ->attachData("in-memory body\n", 'memo.txt', 'text/plain')
                ->send();
        } finally {
            unlink($path);
        }

        $delivered = $this->mailpit->message($receipt->messageId);
        $attachments = $delivered['Attachments'];
        self::assertCount(2, $attachments);
        self::assertSame('notes.txt', $attachments[0]['FileName']);
        self::assertSame('memo.txt', $attachments[1]['FileName']);
        self::assertSame('text/plain', $attachments[1]['ContentType']);
        self::assertSame(
            'attachment body from disk',
            $this->mailpit->part($receipt->messageId, $attachments[0]['PartID']),
        );
        self::assertSame(
            "in-memory body\n",
            $this->mailpit->part($receipt->messageId, $attachments[1]['PartID']),
        );
    }

    public function testTheReceiptCarriesTheSmtpTranscript(): void
    {
        $receipt = $this->mailer()->message()
            ->from('no-reply@example.com')
            ->to('alice@example.com')
            ->subject('Transcript')
            ->text('x')
            ->send();

        self::assertStringContainsString('EHLO', $receipt->debug);
        self::assertStringContainsString('MAIL FROM:<no-reply@example.com>', $receipt->debug);
        self::assertStringContainsString('RCPT TO:<alice@example.com>', $receipt->debug);
        self::assertStringContainsString('250', $receipt->debug);
    }
}
