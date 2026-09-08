<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Unit\Identities;

use PHPdot\Iam\Identities\IdentityContext;
use PHPdot\Iam\Identities\Request\CliRequestDetails;
use PHPdot\Iam\Identities\Request\WebRequestDetails;
use PHPdot\Iam\Identities\Types\CliIdentity;
use PHPdot\Iam\Identities\Types\GuestIdentity;
use PHPdot\Iam\Identities\Types\UserIdentity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IdentityTest extends TestCase
{
    #[Test]
    public function userIdentityCarriesIdAndType(): void
    {
        $identity = new UserIdentity(42);

        self::assertSame(42, $identity->id());
        self::assertSame('user', $identity->type());
    }

    #[Test]
    public function guestIdentityHasNoId(): void
    {
        $identity = new GuestIdentity();

        self::assertNull($identity->id());
        self::assertSame('guest', $identity->type());
    }

    #[Test]
    public function cliIdentityIsFixed(): void
    {
        $identity = new CliIdentity();

        self::assertSame('cli', $identity->id());
        self::assertSame('cli', $identity->type());
    }

    #[Test]
    public function webRequestDetailsExposesChannelAndAttributes(): void
    {
        $request = new WebRequestDetails(ip: '127.0.0.1', userAgent: 'Test/1.0', country: 'JO');

        self::assertSame('web', $request->channel());
        self::assertSame('127.0.0.1', $request->ip());
        self::assertSame('JO', $request->country());
    }

    #[Test]
    public function cliRequestDetailsExposesCommand(): void
    {
        $request = new CliRequestDetails(command: 'migrate');

        self::assertSame('cli', $request->channel());
        self::assertSame('migrate', $request->command());
    }

    #[Test]
    public function identityContextHoldsCurrentIdentityAndRequest(): void
    {
        $context = new IdentityContext();

        self::assertNull($context->current());
        self::assertNull($context->request());

        $identity = new UserIdentity('abc');
        $request = new WebRequestDetails(ip: '10.0.0.1');

        $context->setCurrent($identity);
        $context->setRequest($request);

        self::assertSame($identity, $context->current());
        self::assertSame($request, $context->request());
    }
}
