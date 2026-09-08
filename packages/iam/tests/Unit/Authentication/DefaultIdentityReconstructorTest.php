<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Unit\Authentication;

use PHPdot\Iam\Authentication\DefaultIdentityReconstructor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DefaultIdentityReconstructorTest extends TestCase
{
    #[Test]
    public function reconstructsEachBuiltInKind(): void
    {
        $reconstructor = new DefaultIdentityReconstructor();

        self::assertSame('user', $reconstructor->reconstruct(42, 'user')?->type());
        self::assertSame(42, $reconstructor->reconstruct(42, 'user')?->id());
        self::assertSame('guest', $reconstructor->reconstruct(null, 'guest')?->type());
        self::assertSame('cli', $reconstructor->reconstruct(null, 'cli')?->type());
    }

    #[Test]
    public function userRequiresAnId(): void
    {
        $reconstructor = new DefaultIdentityReconstructor();

        self::assertNull($reconstructor->reconstruct(null, 'user'));
    }

    #[Test]
    public function unknownTypeYieldsNull(): void
    {
        $reconstructor = new DefaultIdentityReconstructor();

        self::assertNull($reconstructor->reconstruct(1, 'robot'));
    }
}
