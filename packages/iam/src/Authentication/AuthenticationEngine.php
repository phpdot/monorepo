<?php

declare(strict_types=1);

/**
 * Policy-driven authentication engine — the mechanism, not the rules.
 *
 * A single attempt() does not walk a fixed list. It identifies the user (path
 * selected by AuthenticationStageInterface::supports()), asks the application's
 * AuthenticationPolicyInterface for the ordered proofs still required, and enforces them
 * one at a time. The first requirement not satisfiable from the current request
 * persists a PendingAuth and returns Pending; the next request resumes at the
 * cursor. A session is issued only once every requirement is met — and the
 * caller, not the engine, issues it via the AuthenticatorInterface.
 *
 * The engine never invents or reorders requirements (that is the policy's job)
 * and fails closed: a required factor with no matching stage never completes a
 * login. PendingAuth lives entirely here and is cleared before authorization or
 * AuthenticatorInterface::current() see anything.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication;

use PHPdot\Iam\Authentication\Contract\AuthenticationPolicyInterface;
use PHPdot\Iam\Authentication\Contract\AuthenticationStageInterface;
use PHPdot\Iam\Authentication\Contract\CredentialsInterface;
use PHPdot\Iam\Authentication\Contract\IdentifiesAccountInterface;
use PHPdot\Iam\Authentication\Contract\LoginThrottleInterface;
use PHPdot\Iam\Authentication\Contract\PendingAuthStoreInterface;
use PHPdot\Iam\Clock\SystemClock;
use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;
use Psr\Clock\ClockInterface;

final readonly class AuthenticationEngine
{
    /**
     * @param list<AuthenticationStageInterface> $stages
     * @param ClockInterface $clock Reads the wall clock for pending-auth expiry
     * @param int $pendingTtl Seconds a half-finished login may live before it expires closed
     * @param LoginThrottleInterface $throttle Consulted around every attempt
     */
    public function __construct(
        private array $stages,
        private AuthenticationPolicyInterface $policy,
        private PendingAuthStoreInterface $store,
        private ClockInterface $clock = new SystemClock(),
        private int $pendingTtl = 300,
        private LoginThrottleInterface $throttle = new NullThrottle(),
    ) {}

    /**
     * @param array<string, mixed> $attributes Request context the app supplies (ip, country, device, …).
     */
    public function attempt(CredentialsInterface $credentials, array $attributes = []): AuthenticationResult
    {
        $pending = $this->store->get();

        if ($pending !== null && $pending->isComplete()) {
            $this->store->clear();

            return AuthenticationResult::failed('inconsistent_pending');
        }

        $throttleKey = $pending !== null
            ? 'pending:' . $pending->identity->type() . ':' . (string) ($pending->identity->id() ?? '')
            : 'account:' . ($credentials instanceof IdentifiesAccountInterface
                ? $credentials->accountIdentifier()
                : $credentials::class);

        if (!$this->throttle->allows($throttleKey)) {
            return AuthenticationResult::failed('throttled');
        }

        if ($pending !== null) {
            if ($pending->isExpired($this->clock->now())) {
                $this->store->clear();

                return AuthenticationResult::failed('pending_expired');
            }

            return $this->concluded($this->resume($pending, $credentials, $attributes), $throttleKey);
        }

        return $this->concluded($this->begin($credentials, $attributes), $throttleKey);
    }

    /**
     * Feed the attempt's outcome back to the throttle: a failure counts, a
     * completed login forgives, a pending step accumulates nothing.
     *
     * @param AuthenticationResult $result The attempt's outcome
     * @param string $throttleKey The key the attempt ran under
     *
     * @return AuthenticationResult
     */
    private function concluded(AuthenticationResult $result, string $throttleKey): AuthenticationResult
    {
        if ($result->isFailed()) {
            $this->throttle->registerFailure($throttleKey);
        } elseif ($result->isAuthenticated()) {
            $this->throttle->clear($throttleKey);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function begin(CredentialsInterface $credentials, array $attributes): AuthenticationResult
    {
        $stage = $this->identifyingStage($credentials);
        if ($stage === null) {
            return AuthenticationResult::failed('no_supporting_stage');
        }

        $result = $stage($credentials);
        if (!$result->isAuthenticated() || $result->identity === null) {
            return $result->isFailed() ? $result : AuthenticationResult::failed('identification_failed');
        }

        $identity = $result->identity;
        $requirements = $this->policy->requirementsFor(
            new AuthenticationContext($identity, [$stage->factor()], $attributes),
        );

        if ($requirements->isEmpty()) {
            return AuthenticationResult::authenticated($identity);
        }

        foreach ($requirements->requirements as $requirement) {
            if ($this->stageByFactor($requirement->factor) === null) {
                return AuthenticationResult::failed('unsatisfiable_requirement');
            }
        }

        $pending = new PendingAuth(
            $identity,
            $requirements,
            0,
            $attributes,
            $this->clock->now()->modify(sprintf('+%d seconds', $this->pendingTtl)),
            [$stage->factor()],
        );
        $this->store->put($pending);

        return AuthenticationResult::pending($pending);
    }

    /**
     * Continue an in-flight login. A wrong-credential step fails but KEEPS the
     * pending — the correct factor can still be submitted — while a failed
     * proof or a factor proving a different identity ends the attempt and
     * clears the store.
     *
     * @param array<string, mixed> $attributes
     */
    private function resume(PendingAuth $pending, CredentialsInterface $credentials, array $attributes): AuthenticationResult
    {
        $requirement = $pending->current();
        $stage = $this->stageByFactor($requirement->factor);

        if ($stage === null) {
            $this->store->clear();

            return AuthenticationResult::failed('unsatisfiable_requirement');
        }

        if (!$stage->supports($credentials)) {
            return AuthenticationResult::failed('unexpected_factor');
        }

        $result = $stage($credentials);

        if (!$result->isAuthenticated() || $result->identity === null) {
            $this->store->clear();

            return $result->isFailed() ? $result : AuthenticationResult::failed('factor_failed');
        }

        if (!$this->sameIdentity($result->identity, $pending->identity)) {
            $this->store->clear();

            return AuthenticationResult::failed('factor_identity_mismatch');
        }

        $merged = array_merge($pending->attributes, $attributes);
        $proven = array_values(array_unique([
            ...$pending->provenFactors,
            $pending->current()->factor,
        ]));

        $fresh = $this->policy->requirementsFor(
            new AuthenticationContext($pending->identity, $proven, $merged),
        );

        $remaining = array_values(array_filter(
            $fresh->requirements,
            static fn(Requirement $requirement): bool => !in_array($requirement->factor, $proven, true),
        ));

        if ($fresh->isEmpty() || $remaining === []) {
            $this->store->clear();

            return AuthenticationResult::authenticated($pending->identity);
        }

        foreach ($remaining as $demand) {
            if ($this->stageByFactor($demand->factor) === null) {
                $this->store->clear();

                return AuthenticationResult::failed('unsatisfiable_requirement');
            }
        }

        $rebuilt = new PendingAuth(
            $pending->identity,
            new RequirementSet($remaining),
            0,
            $merged,
            $pending->expiresAt,
            $proven,
        );
        $this->store->put($rebuilt);

        return AuthenticationResult::pending($rebuilt);
    }

    private function identifyingStage(CredentialsInterface $credentials): null|AuthenticationStageInterface
    {
        foreach ($this->stages as $stage) {
            if ($stage->supports($credentials)) {
                return $stage;
            }
        }

        return null;
    }

    private function stageByFactor(string $factor): null|AuthenticationStageInterface
    {
        foreach ($this->stages as $stage) {
            if ($stage->factor() === $factor) {
                return $stage;
            }
        }

        return null;
    }

    private function sameIdentity(IdentityInterface $a, IdentityInterface $b): bool
    {
        return $a->id() === $b->id() && $a->type() === $b->type();
    }
}
