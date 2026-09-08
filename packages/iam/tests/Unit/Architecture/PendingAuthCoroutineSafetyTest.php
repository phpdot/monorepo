<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Unit\Architecture;

use PHPdot\Container\Attribute\Scoped;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\Iam\Authentication\AuthenticationContext;
use PHPdot\Iam\Authentication\AuthenticationEngine;
use PHPdot\Iam\Authentication\PendingAuth;
use PHPdot\Iam\Authentication\Requirement;
use PHPdot\Iam\Authentication\RequirementSet;
use PHPdot\Iam\Authentication\SessionPendingAuthStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * MFA-specific coroutine-safety guarantees, complementing the generic guard.
 *
 * The half-finished-auth store wraps the per-request session, so it MUST be
 * #[Scoped] (a fresh instance per coroutine) and never shared — a guarantee the
 * generic guard does not catch for a readonly session wrapper (it only requires
 * #[Scoped] for classes with mutable instance state). Sharing a pending store
 * would leak one request's half-login into another coroutine.
 */
final class PendingAuthCoroutineSafetyTest extends TestCase
{
    #[Test]
    public function pendingAuthStoreIsScopedNeverSingleton(): void
    {
        $reflection = new ReflectionClass(SessionPendingAuthStore::class);

        self::assertNotEmpty(
            $reflection->getAttributes(Scoped::class),
            'SessionPendingAuthStore must be #[Scoped] — it wraps the per-coroutine session.',
        );
        self::assertEmpty(
            $reflection->getAttributes(Singleton::class),
            'SessionPendingAuthStore must never be #[Singleton] — sharing it leaks a half-login across coroutines.',
        );
    }

    /**
     * The engine and the MFA value objects carry no mutable state, so they are
     * safe to construct once and share — assert that stays true.
     *
     * @param class-string $class
     */
    #[DataProvider('immutableClasses')]
    #[Test]
    public function engineAndPendingValueObjectsAreReadonly(string $class): void
    {
        self::assertTrue(
            (new ReflectionClass($class))->isReadOnly(),
            $class . ' must be readonly to be coroutine-safe.',
        );
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function immutableClasses(): iterable
    {
        yield 'engine' => [AuthenticationEngine::class];
        yield 'pending-auth' => [PendingAuth::class];
        yield 'requirement-set' => [RequirementSet::class];
        yield 'requirement' => [Requirement::class];
        yield 'context' => [AuthenticationContext::class];
    }
}
