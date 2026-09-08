<?php

declare(strict_types=1);

namespace PHPdot\Mail\Tests\Unit;

use PHPdot\Mail\Exception\InvalidHeaderNameException;
use PHPdot\Mail\Exception\MailException;
use PHPdot\Mail\Message\Message;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MessageTest extends TestCase
{
    public function testIsImmutable(): void
    {
        $base = new Message();
        $withSubject = $base->subject('Hi');

        self::assertNotSame($base, $withSubject);
        self::assertSame('', $base->subjectLine());
        self::assertSame('Hi', $withSubject->subjectLine());
    }

    public function testAccumulatesRecipients(): void
    {
        $message = (new Message())
            ->to('a@example.com', 'A')
            ->to('b@example.com')
            ->cc('c@example.com');

        self::assertCount(2, $message->recipients());
        self::assertSame('a@example.com', $message->recipients()[0]->email);
        self::assertSame('A', $message->recipients()[0]->name);
        self::assertSame('b@example.com', $message->recipients()[1]->email);
        self::assertCount(1, $message->carbonCopies());
    }

    public function testBuildsEveryField(): void
    {
        $message = (new Message())
            ->from('no-reply@example.com', 'X')
            ->bcc('b@example.com')
            ->replyTo('reply@example.com')
            ->subject('Hello')
            ->text('plain')
            ->html('<p>rich</p>')
            ->priority(1)
            ->header('X-Tag', 'welcome');

        self::assertSame('no-reply@example.com', $message->sender()?->email);
        self::assertSame('Hello', $message->subjectLine());
        self::assertSame('plain', $message->textBody());
        self::assertSame('<p>rich</p>', $message->htmlBody());
        self::assertSame(1, $message->priorityLevel());
        self::assertSame(['X-Tag' => 'welcome'], $message->customHeaders());
        self::assertCount(1, $message->blindCarbonCopies());
        self::assertCount(1, $message->replyAddresses());
    }

    public function testCollectsAttachments(): void
    {
        $message = (new Message())
            ->attach('/tmp/invoice.pdf', 'invoice.pdf')
            ->attachData('raw bytes', 'note.txt', 'text/plain');

        self::assertCount(2, $message->attachments());
        self::assertSame('/tmp/invoice.pdf', $message->attachments()[0]->path);
        self::assertSame('invoice.pdf', $message->attachments()[0]->name);
        self::assertSame('raw bytes', $message->attachments()[1]->body);
        self::assertSame('note.txt', $message->attachments()[1]->name);
    }

    public function testDefaultsAreEmpty(): void
    {
        $message = new Message();

        self::assertNull($message->sender());
        self::assertSame([], $message->recipients());
        self::assertSame('', $message->subjectLine());
        self::assertNull($message->htmlBody());
        self::assertNull($message->textBody());
        self::assertNull($message->priorityLevel());
        self::assertSame([], $message->attachments());
    }

    public function testSendingAnUnboundMessageThrows(): void
    {
        $this->expectException(MailException::class);

        (new Message())->send();
    }

    public function testAcceptsEveryPrintableAsciiFieldName(): void
    {
        $message = (new Message())->header("X-Safe!#%&'*+_-|~.9", 'welcome');

        self::assertSame(["X-Safe!#%&'*+_-|~.9" => 'welcome'], $message->customHeaders());
    }

    #[DataProvider('invalidHeaderNameProvider')]
    public function testRejectsInvalidHeaderNames(string $name): void
    {
        try {
            (new Message())->header($name, 'value');
            self::fail('an invalid header name must throw');
        } catch (InvalidHeaderNameException $e) {
            self::assertSame($name, $e->getHeaderName());
            self::assertStringNotContainsString("\n", $e->getMessage());
            self::assertStringNotContainsString("\r", $e->getMessage());
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidHeaderNameProvider(): array
    {
        return [
            'empty' => [''],
            'line break' => ["X-Bad\r\nBcc: evil"],
            'colon' => ['X-Bad: Name'],
            'space' => ['X Bad'],
            'tab' => ["X-Bad\tName"],
            'non-ascii' => ['X-Bäd'],
            'control byte' => ["X-Bad\x01"],
            'nul byte' => ["X-Bad\x00"],
        ];
    }
}
