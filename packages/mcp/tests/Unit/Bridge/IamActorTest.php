<?php

declare(strict_types=1);

namespace PHPdot\Mcp\Tests\Unit\Bridge;

use PHPdot\Http\Message\ServerRequest;
use PHPdot\Mcp\Bridge\IamActor;
use PHPdot\Mcp\Bridge\IamActorResolver;
use PHPdot\Mcp\Tests\Support\FakeAuthorizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IamActorTest extends TestCase
{
    #[Test]
    public function theActorDelegatesToTheAuthorizer(): void
    {
        $actor = new IamActor(new FakeAuthorizer(['catalog.view'], identified: true));

        self::assertTrue($actor->can('catalog.view'));
        self::assertFalse($actor->can('catalog.purge'));
    }

    #[Test]
    public function anIdentifiedAuthorizerResolvesToAnActor(): void
    {
        $resolver = new IamActorResolver(new FakeAuthorizer(['catalog.view'], identified: true));

        $actor = $resolver->resolve(new ServerRequest('POST', '/mcp'));

        self::assertInstanceOf(IamActor::class, $actor);
        self::assertTrue($actor->can('catalog.view'));
    }

    #[Test]
    public function anAnonymousAuthorizerResolvesToNobody(): void
    {
        $resolver = new IamActorResolver(new FakeAuthorizer([], identified: false));

        self::assertNull($resolver->resolve(new ServerRequest('POST', '/mcp')));
    }
}
