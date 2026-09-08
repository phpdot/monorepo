<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Unit\Authentication;

use PHPdot\Iam\Authentication\PasswordCredentials;
use PHPdot\Iam\Authentication\Stages\PasswordStage;
use PHPdot\Iam\Authentication\StoredCredentials;
use PHPdot\Iam\Identities\Types\UserIdentity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PasswordStageTest extends TestCase
{
    #[Test]
    public function supportsOnlyPasswordCredentials(): void
    {
        $stage = new PasswordStage(new SpyCredentialsProvider(), new SpyPasswordHasher());

        self::assertTrue($stage->supports(new PasswordCredentials('a', 'b')));
        self::assertFalse($stage->supports(new OtpCredentials('code')));
    }

    #[Test]
    public function unknownIdentifierFailsWithTheSameReasonAndPasswordEqualizesTiming(): void
    {
        $hasher = new SpyPasswordHasher();
        $stage = new PasswordStage(new SpyCredentialsProvider(stored: null), $hasher);

        $result = $stage(new PasswordCredentials('nobody', 'pw'));

        self::assertTrue($result->isFailed());
        self::assertSame('invalid_credentials', $result->reason);
        self::assertSame(1, $hasher->hashCalls, 'a miss must run a hash to equalize timing with the verify path');
    }

    #[Test]
    public function wrongPasswordFailsWithoutEqualizing(): void
    {
        $provider = new SpyCredentialsProvider(stored: new StoredCredentials(new UserIdentity('alice'), 'hash'));
        $hasher = new SpyPasswordHasher(verifies: false);
        $stage = new PasswordStage($provider, $hasher);

        $result = $stage(new PasswordCredentials('alice', 'wrong'));

        self::assertTrue($result->isFailed());
        self::assertSame('invalid_credentials', $result->reason);
        self::assertSame(0, $hasher->hashCalls, 'a known user runs verify, not the equalization hash');
    }

    #[Test]
    public function correctPasswordAuthenticatesWithTheStoredIdentity(): void
    {
        $alice = new UserIdentity('alice');
        $provider = new SpyCredentialsProvider(stored: new StoredCredentials($alice, 'hash'));
        $hasher = new SpyPasswordHasher(verifies: true);
        $stage = new PasswordStage($provider, $hasher);

        $result = $stage(new PasswordCredentials('alice', 'correct'));

        self::assertTrue($result->isAuthenticated());
        self::assertSame('alice', $result->identity?->id());
    }
}
