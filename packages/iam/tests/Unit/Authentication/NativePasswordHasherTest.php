<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Unit\Authentication;

use PHPdot\Iam\Authentication\NativePasswordHasher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NativePasswordHasherTest extends TestCase
{
    #[Test]
    public function hashAndVerifyRoundtrip(): void
    {
        $hasher = new NativePasswordHasher();

        $hash = $hasher->hash('correct horse battery staple');

        self::assertNotSame('correct horse battery staple', $hash);
        self::assertTrue($hasher->verify('correct horse battery staple', $hash));
        self::assertFalse($hasher->verify('a different secret', $hash));
    }

    #[Test]
    public function hashesAreArgon2idWithExplicitCosts(): void
    {
        $hash = new NativePasswordHasher()->hash('secret');

        self::assertStringStartsWith('$argon2id$', $hash);
        self::assertStringContainsString('m=65536,t=4,p=1', $hash);
    }

    #[Test]
    public function aFreshHashNeedsNoRehash(): void
    {
        $hasher = new NativePasswordHasher();

        self::assertFalse($hasher->needsRehash($hasher->hash('secret')));
    }

    #[Test]
    public function aLegacyBcryptHashVerifiesAndReportsNeedsRehash(): void
    {
        $hasher = new NativePasswordHasher();
        $legacy = password_hash('secret', PASSWORD_BCRYPT);

        self::assertTrue($hasher->verify('secret', $legacy));
        self::assertTrue($hasher->needsRehash($legacy));
    }
}
