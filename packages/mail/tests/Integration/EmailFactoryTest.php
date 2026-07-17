<?php

declare(strict_types=1);

namespace PHPdot\Mail\Tests\Integration;

use PHPdot\Mail\MailConfig;
use PHPdot\Mail\Message\Message;
use PHPdot\Mail\Transport\EmailFactory;
use PHPUnit\Framework\TestCase;

final class EmailFactoryTest extends TestCase
{
    private EmailFactory $factory;
    private MailConfig $config;

    protected function setUp(): void
    {
        $this->factory = new EmailFactory();
        $this->config = new MailConfig(dsn: 'null://null');
    }

    public function testMapsEveryFieldOntoTheMimeEmail(): void
    {
        $message = (new Message())
            ->from('no-reply@example.com', 'App')
            ->to('alice@example.com', 'Alice')
            ->to('bob@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->replyTo('reply@example.com')
            ->subject('Welcome')
            ->text('plain text')
            ->html('<p>rich</p>')
            ->priority(2)
            ->header('X-Campaign', 'welcome');

        $email = $this->factory->create($message, $this->config);

        self::assertSame('no-reply@example.com', $email->getFrom()[0]->getAddress());
        self::assertSame('App', $email->getFrom()[0]->getName());

        self::assertCount(2, $email->getTo());
        self::assertSame('alice@example.com', $email->getTo()[0]->getAddress());
        self::assertSame('Alice', $email->getTo()[0]->getName());
        self::assertSame('bob@example.com', $email->getTo()[1]->getAddress());

        self::assertSame('cc@example.com', $email->getCc()[0]->getAddress());
        self::assertSame('bcc@example.com', $email->getBcc()[0]->getAddress());
        self::assertSame('reply@example.com', $email->getReplyTo()[0]->getAddress());

        self::assertSame('Welcome', $email->getSubject());
        self::assertSame('plain text', $email->getTextBody());
        self::assertSame('<p>rich</p>', $email->getHtmlBody());
        self::assertSame(2, $email->getPriority());
        self::assertTrue($email->getHeaders()->has('X-Campaign'));
    }

    public function testFallsBackToTheConfigSenderWhenMessageHasNone(): void
    {
        $config = new MailConfig(dsn: 'null://null', fromEmail: 'sender@example.com', fromName: 'Sender');

        $email = $this->factory->create((new Message())->to('a@example.com'), $config);

        self::assertSame('sender@example.com', $email->getFrom()[0]->getAddress());
        self::assertSame('Sender', $email->getFrom()[0]->getName());
    }

    public function testMapsAttachments(): void
    {
        $message = (new Message())
            ->to('a@example.com')
            ->attachData('hello bytes', 'note.txt', 'text/plain');

        self::assertCount(1, $this->factory->create($message, $this->config)->getAttachments());
    }
}
