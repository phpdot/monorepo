<?php

declare(strict_types=1);

namespace PHPdot\Mail\Gateway\Tests\Unit\Delivery;

use PHPdot\Mail\Gateway\Delivery\SesClientInterface;
use PHPdot\Mail\Gateway\Delivery\SesTransport;
use PHPdot\Mail\Gateway\Exception\DeliveryException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SesTransportTest extends TestCase
{
    #[Test]
    public function sendDelegatesToClient(): void
    {
        $client = $this->createMock(SesClientInterface::class);
        $client->expects(self::once())
            ->method('sendRawEmail')
            ->with('raw-eml', 'sender@test.com', ['rcpt@test.com'])
            ->willReturn('ses-msg-id-123');

        $transport = new SesTransport($client);
        $result = $transport->send('raw-eml', 'sender@test.com', ['rcpt@test.com']);

        self::assertSame('ses-msg-id-123', $result);
    }

    #[Test]
    public function sendWrapsClientExceptions(): void
    {
        $client = $this->createStub(SesClientInterface::class);
        $client->method('sendRawEmail')
            ->willThrowException(new \RuntimeException('AWS error'));

        $transport = new SesTransport($client);

        $this->expectException(DeliveryException::class);
        $this->expectExceptionMessage('SES delivery failed: AWS error');
        $transport->send('eml', 'a@b.com', ['c@d.com']);
    }

    #[Test]
    public function nameReturnsSes(): void
    {
        $client = $this->createStub(SesClientInterface::class);
        $transport = new SesTransport($client);

        self::assertSame('ses', $transport->name());
    }

    #[Test]
    public function sendPassesMultipleRecipients(): void
    {
        $recipients = ['a@test.com', 'b@test.com', 'c@test.com'];

        $client = $this->createMock(SesClientInterface::class);
        $client->expects(self::once())
            ->method('sendRawEmail')
            ->with('eml', 'sender@test.com', $recipients)
            ->willReturn('id');

        $transport = new SesTransport($client);
        $transport->send('eml', 'sender@test.com', $recipients);
    }
}
