<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Unit\Authentication;

use PHPdot\Iam\Authentication\AuthenticationEngine;
use PHPdot\Iam\Authentication\AuthenticationResult;
use PHPdot\Iam\Authentication\Contract\LoginThrottleInterface;
use PHPdot\Iam\Authentication\NullThrottle;
use PHPdot\Iam\Authentication\PasswordCredentials;
use PHPdot\Iam\Authentication\PendingAuth;
use PHPdot\Iam\Authentication\Requirement;
use PHPdot\Iam\Authentication\RequirementSet;
use PHPdot\Iam\Clock\SystemClock;
use PHPdot\Iam\Identities\Types\UserIdentity;
use PHPdot\Iam\Tests\Support\FrozenClock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class AuthenticationEngineTest extends TestCase
{
    #[Test]
    public function beginFailsWhenNoStageSupportsTheCredentials(): void
    {
        $engine = $this->engine(stages: [], policy: $this->policy(null), store: $store = new MemoryPendingAuthStore());

        $result = $engine->attempt(new PasswordCredentials('alice', 'pw'));

        self::assertTrue($result->isFailed());
        self::assertSame('no_supporting_stage', $result->reason);
        self::assertNull($store->get());
    }

    #[Test]
    public function beginAuthenticatesImmediatelyWhenPolicyRequiresNothing(): void
    {
        $alice = new UserIdentity('alice');
        $engine = $this->engine(
            stages: [$this->passwordStage($alice)],
            policy: $this->policy(null),
            store: new MemoryPendingAuthStore(),
        );

        $result = $engine->attempt(new PasswordCredentials('alice', 'pw'));

        self::assertTrue($result->isAuthenticated());
        self::assertEquals($alice, $result->identity);
    }

    #[Test]
    public function beginFailsWhenARequiredFactorHasNoStage(): void
    {
        $alice = new UserIdentity('alice');
        $engine = $this->engine(
            stages: [$this->passwordStage($alice)],
            policy: $this->policy(new RequirementSet([new Requirement('totp')])),
            store: $store = new MemoryPendingAuthStore(),
        );

        $result = $engine->attempt(new PasswordCredentials('alice', 'pw'));

        self::assertTrue($result->isFailed());
        self::assertSame('unsatisfiable_requirement', $result->reason);
        self::assertNull($store->get());
    }

    #[Test]
    public function beginPersistsPendingWhenFurtherProofsAreRequired(): void
    {
        $alice = new UserIdentity('alice');
        $store = new MemoryPendingAuthStore();
        $engine = $this->engine(
            stages: [$this->passwordStage($alice), $this->otpStage($alice)],
            policy: $this->policy(new RequirementSet([new Requirement('otp')])),
            store: $store,
        );

        $result = $engine->attempt(new PasswordCredentials('alice', 'pw'));

        self::assertTrue($result->isPending());
        self::assertNotNull($store->get());
        self::assertSame(0, $store->get()?->satisfied);
    }

    #[Test]
    public function resumeRejectsWrongCredentialButKeepsPending(): void
    {
        $alice = new UserIdentity('alice');
        $store = new MemoryPendingAuthStore();
        $engine = $this->engine(
            stages: [$this->passwordStage($alice), $this->otpStage($alice)],
            policy: $this->policy(new RequirementSet([new Requirement('otp')])),
            store: $store,
        );

        $engine->attempt(new PasswordCredentials('alice', 'pw'));
        self::assertNotNull($store->get());

        $result = $engine->attempt(new PasswordCredentials('alice', 'pw'));

        self::assertTrue($result->isFailed());
        self::assertSame('unexpected_factor', $result->reason);
        self::assertNotNull($store->get(), 'pending must be retained for the correct factor');
    }

    #[Test]
    public function resumeFailsAndClearsWhenTheProofFails(): void
    {
        $alice = new UserIdentity('alice');
        $store = new MemoryPendingAuthStore();
        $engine = $this->engine(
            stages: [$this->passwordStage($alice), $this->otpStage($alice, success: false)],
            policy: $this->policy(new RequirementSet([new Requirement('otp')])),
            store: $store,
        );

        $engine->attempt(new PasswordCredentials('alice', 'pw'));
        $result = $engine->attempt(new OtpCredentials('wrong'));

        self::assertTrue($result->isFailed());
        self::assertSame('bad_code', $result->reason);
        self::assertNull($store->get());
    }

    #[Test]
    public function resumeRejectsAFactorProvingADifferentIdentity(): void
    {
        $alice = new UserIdentity('alice');
        $bob = new UserIdentity('bob');
        $store = new MemoryPendingAuthStore();
        $engine = $this->engine(
            stages: [$this->passwordStage($alice), $this->otpStage($bob)],
            policy: $this->policy(new RequirementSet([new Requirement('otp')])),
            store: $store,
        );

        $engine->attempt(new PasswordCredentials('alice', 'pw'));
        $result = $engine->attempt(new OtpCredentials('code'));

        self::assertTrue($result->isFailed());
        self::assertSame('factor_identity_mismatch', $result->reason);
        self::assertNull($store->get());
    }

    #[Test]
    public function resumeCompletesAndClearsOnTheLastFactor(): void
    {
        $alice = new UserIdentity('alice');
        $store = new MemoryPendingAuthStore();
        $engine = $this->engine(
            stages: [$this->passwordStage($alice), $this->otpStage($alice)],
            policy: $this->policy(new RequirementSet([new Requirement('otp')])),
            store: $store,
        );

        $engine->attempt(new PasswordCredentials('alice', 'pw'));
        $result = $engine->attempt(new OtpCredentials('correct'));

        self::assertTrue($result->isAuthenticated());
        self::assertEquals($alice, $result->identity);
        self::assertNull($store->get());
    }

    #[Test]
    public function resumeAdvancesThroughMultipleOrderedFactors(): void
    {
        $alice = new UserIdentity('alice');
        $store = new MemoryPendingAuthStore();
        $engine = $this->engine(
            stages: [$this->passwordStage($alice), $this->otpStage($alice), $this->emailStage($alice)],
            policy: $this->policy(new RequirementSet([new Requirement('otp'), new Requirement('email')])),
            store: $store,
        );

        $first = $engine->attempt(new PasswordCredentials('alice', 'pw'));
        self::assertTrue($first->isPending());
        self::assertSame(0, $store->get()?->satisfied);

        $second = $engine->attempt(new OtpCredentials('correct'));
        self::assertTrue($second->isPending());
        self::assertSame('email', $store->get()?->current()->factor);

        $third = $engine->attempt(new EmailCredentials('token'));
        self::assertTrue($third->isAuthenticated());
        self::assertNull($store->get());
    }

    #[Test]
    public function anExpiredPendingFailsClosedAndClears(): void
    {
        $alice = new UserIdentity('alice');
        $clock = new FrozenClock(1_000_000);
        $store = new MemoryPendingAuthStore();
        $engine = $this->engine(
            stages: [$this->passwordStage($alice), $this->otpStage($alice)],
            policy: $this->policy(new RequirementSet([new Requirement('otp')])),
            store: $store,
            clock: $clock,
            pendingTtl: 300,
        );

        $engine->attempt(new PasswordCredentials('alice', 'pw'));
        self::assertNotNull($store->get());

        $clock->advance(301);

        $result = $engine->attempt(new OtpCredentials('correct'));

        self::assertTrue($result->isFailed());
        self::assertSame('pending_expired', $result->reason);
        self::assertNull($store->get());
    }

    #[Test]
    public function anInconsistentCompletePendingIsRejectedAsAFailure(): void
    {
        $alice = new UserIdentity('alice');
        $store = new MemoryPendingAuthStore();
        $store->put(new PendingAuth($alice, new RequirementSet([new Requirement('otp')]), 1, [], null, ['password']));
        $engine = $this->engine(
            stages: [$this->passwordStage($alice), $this->otpStage($alice)],
            policy: $this->policy(new RequirementSet([new Requirement('otp')])),
            store: $store,
        );

        $result = $engine->attempt(new OtpCredentials('correct'));

        self::assertTrue($result->isFailed());
        self::assertSame('inconsistent_pending', $result->reason);
        self::assertNull($store->get());
    }

    #[Test]
    public function resumeReConsultsThePolicyWithMergedAttributesAndFullProof(): void
    {
        $alice = new UserIdentity('alice');
        $policy = new FakePolicy();
        $policy->scripted = [
            new RequirementSet([new Requirement('otp')]),
            new RequirementSet([new Requirement('otp'), new Requirement('email')]),
        ];
        $store = new MemoryPendingAuthStore();
        $engine = $this->engine(
            stages: [$this->passwordStage($alice), $this->otpStage($alice), $this->emailStage($alice)],
            policy: $policy,
            store: $store,
        );

        $engine->attempt(new PasswordCredentials('alice', 'pw'), ['country' => 'home', 'device' => 'laptop']);
        $result = $engine->attempt(new OtpCredentials('correct'), ['country' => 'far']);

        $second = $policy->seen[1];

        self::assertSame(['password', 'otp'], $second->completedFactors, 'the full proof is visible to the re-consulted policy');
        self::assertSame('far', $second->attributes['country'], 'fresh request attributes override the frozen snapshot');
        self::assertSame('laptop', $second->attributes['device'], 'unoverridden snapshot attributes survive');
        self::assertTrue($result->isPending());
        self::assertSame('email', $store->get()?->current()->factor, 'a tightened policy adds its new demand mid-flow');
    }

    #[Test]
    public function aPolicyThatRelentsCompletesTheLoginEarly(): void
    {
        $alice = new UserIdentity('alice');
        $policy = new FakePolicy();
        $policy->scripted = [
            new RequirementSet([new Requirement('otp'), new Requirement('email')]),
            null,
        ];
        $store = new MemoryPendingAuthStore();
        $engine = $this->engine(
            stages: [$this->passwordStage($alice), $this->otpStage($alice), $this->emailStage($alice)],
            policy: $policy,
            store: $store,
        );

        $engine->attempt(new PasswordCredentials('alice', 'pw'));
        $result = $engine->attempt(new OtpCredentials('correct'));

        self::assertTrue($result->isAuthenticated());
        self::assertNull($store->get());
    }

    #[Test]
    public function aClosedThrottleRefusesBeforeAnyStageRuns(): void
    {
        $alice = new UserIdentity('alice');
        $throttle = new CountingThrottle();
        $throttle->closed = true;
        $engine = $this->engine(
            stages: [$this->passwordStage($alice)],
            policy: $this->policy(null),
            store: new MemoryPendingAuthStore(),
            throttle: $throttle,
        );

        $result = $engine->attempt(new PasswordCredentials('alice', 'pw'));

        self::assertTrue($result->isFailed());
        self::assertSame('throttled', $result->reason);
        self::assertSame([], $throttle->failures, 'a refused attempt accumulates nothing');
    }

    #[Test]
    public function failedAttemptsRegisterAgainstTheAccountKey(): void
    {
        $throttle = new CountingThrottle();
        $engine = $this->engine(
            stages: [$this->passwordStage(new UserIdentity('alice'), success: false)],
            policy: $this->policy(null),
            store: new MemoryPendingAuthStore(),
            throttle: $throttle,
        );

        $result = $engine->attempt(new PasswordCredentials('alice', 'wrong'));

        self::assertTrue($result->isFailed());
        self::assertSame(['account:alice'], $throttle->failures);
    }

    #[Test]
    public function aCompletedLoginClearsTheThrottleKey(): void
    {
        $throttle = new CountingThrottle();
        $engine = $this->engine(
            stages: [$this->passwordStage(new UserIdentity('alice'))],
            policy: $this->policy(null),
            store: new MemoryPendingAuthStore(),
            throttle: $throttle,
        );

        $result = $engine->attempt(new PasswordCredentials('alice', 'pw'));

        self::assertTrue($result->isAuthenticated());
        self::assertSame(['account:alice'], $throttle->cleared);
    }

    #[Test]
    public function aThrottledResumeKeepsThePendingLogin(): void
    {
        $alice = new UserIdentity('alice');
        $throttle = new CountingThrottle();
        $store = new MemoryPendingAuthStore();
        $engine = $this->engine(
            stages: [$this->passwordStage($alice), $this->otpStage($alice)],
            policy: $this->policy(new RequirementSet([new Requirement('otp')])),
            store: $store,
            throttle: $throttle,
        );

        $engine->attempt(new PasswordCredentials('alice', 'pw'));
        self::assertNotNull($store->get());

        $throttle->closed = true;
        $result = $engine->attempt(new OtpCredentials('correct'));

        self::assertTrue($result->isFailed());
        self::assertSame('throttled', $result->reason);
        self::assertNotNull($store->get(), 'a throttled resume keeps the pending for after the cooldown');
        self::assertSame([], $throttle->failures);
    }

    /**
     * @param list<\PHPdot\Iam\Authentication\Contract\AuthenticationStageInterface> $stages
     */
    private function engine(
        array $stages,
        FakePolicy $policy,
        MemoryPendingAuthStore $store,
        ClockInterface $clock = new SystemClock(),
        int $pendingTtl = 300,
        LoginThrottleInterface $throttle = new NullThrottle(),
    ): AuthenticationEngine {
        return new AuthenticationEngine($stages, $policy, $store, $clock, $pendingTtl, $throttle);
    }

    private function policy(null|RequirementSet $next): FakePolicy
    {
        $policy = new FakePolicy();
        $policy->next = $next;

        return $policy;
    }

    private function passwordStage(UserIdentity $identity, bool $success = true): FakeStage
    {
        return new FakeStage(
            'password',
            PasswordCredentials::class,
            static fn(): AuthenticationResult => $success
                ? AuthenticationResult::authenticated($identity)
                : AuthenticationResult::failed('bad_password'),
        );
    }

    private function otpStage(UserIdentity $identity, bool $success = true): FakeStage
    {
        return new FakeStage(
            'otp',
            OtpCredentials::class,
            static fn(): AuthenticationResult => $success
                ? AuthenticationResult::authenticated($identity)
                : AuthenticationResult::failed('bad_code'),
        );
    }

    private function emailStage(UserIdentity $identity): FakeStage
    {
        return new FakeStage(
            'email',
            EmailCredentials::class,
            static fn(): AuthenticationResult => AuthenticationResult::authenticated($identity),
        );
    }
}
