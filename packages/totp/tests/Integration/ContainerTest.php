<?php

declare(strict_types=1);

namespace PHPdot\Totp\Tests\Integration;

use PHPdot\Container\ContainerBuilder;
use PHPdot\Totp\OtpFactory;
use PHPUnit\Framework\TestCase;

/**
 * Proves the package wires through the real phpdot container: the #[Singleton]
 * attribute is discovered by a directory scan and the factory resolves as one
 * shared instance, then produces a working, verifiable code.
 */
final class ContainerTest extends TestCase
{
    public function test_factory_resolves_as_a_singleton_and_works(): void
    {
        $container = (new ContainerBuilder())
            ->scanAttributesIn(\dirname(__DIR__, 2) . '/src')
            ->build();

        $factory = $container->get(OtpFactory::class);

        self::assertInstanceOf(OtpFactory::class, $factory);
        self::assertSame($factory, $container->get(OtpFactory::class), 'OtpFactory must be a singleton');

        $secret = $factory->generateSecret();
        $totp = $factory->totp($secret);

        self::assertTrue($totp->verify($totp->current())->passed);
    }
}
