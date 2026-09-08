<?php

declare(strict_types=1);

namespace PHPdot\WebAuthn\Tests\Integration;

use PHPdot\Container\ContainerBuilder;
use PHPdot\Container\Testing\TestContextProvider;
use PHPdot\Contracts\Session\SessionInterface;
use PHPdot\WebAuthn\Contract\CeremonyStoreInterface;
use PHPdot\WebAuthn\Contract\CredentialRecordRepositoryInterface;
use PHPdot\WebAuthn\SessionCeremonyStore;
use PHPdot\WebAuthn\Tests\Support\ArraySession;
use PHPdot\WebAuthn\Tests\Support\FrozenClock;
use PHPdot\WebAuthn\Tests\Support\InMemoryCredentialRecords;
use PHPdot\WebAuthn\WebauthnConfig;
use PHPdot\WebAuthn\WebauthnEngine;
use PHPdot\WebAuthn\WebauthnService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Proves the package wires through the real phpdot container: the
 * #[Singleton] engine and the #[Scoped] service are discovered by a
 * directory scan over host-provided seams, and the service is stable within
 * a request and fresh across requests.
 */
final class ContainerTest extends TestCase
{
    #[Test]
    public function theAttributeWiringResolvesOverHostSeams(): void
    {
        $requests = new TestContextProvider();
        $container = (new ContainerBuilder())
            ->withContextProvider($requests)
            ->scanAttributesIn(\dirname(__DIR__, 2) . '/src')
            ->addDefinitions([
                WebauthnConfig::class => new WebauthnConfig(
                    rpId: 'example.test',
                    rpName: 'Example',
                    allowedOrigins: ['https://example.test'],
                ),
                CredentialRecordRepositoryInterface::class => new InMemoryCredentialRecords(),
                SessionInterface::class => new ArraySession(),
                \Psr\Clock\ClockInterface::class => new FrozenClock(1_000),
                CeremonyStoreInterface::class => new SessionCeremonyStore(
                    new ArraySession(),
                    new WebauthnConfig(rpId: 'example.test', allowedOrigins: ['https://example.test']),
                    new FrozenClock(1_000),
                ),
            ])
            ->build();

        $engine = $container->get(WebauthnEngine::class);

        self::assertInstanceOf(WebauthnEngine::class, $engine);

        $first = $container->get(WebauthnService::class);
        self::assertInstanceOf(WebauthnService::class, $first);
        self::assertSame($first, $container->get(WebauthnService::class), 'stable within one request');

        $requests->newContext('next-request');

        self::assertNotSame($first, $container->get(WebauthnService::class), 'fresh across requests');
    }
}
