<?php

declare(strict_types=1);

namespace PHPdot\Ai\Tests\Integration;

use PHPdot\Ai\Registry\DriverFactories;
use PHPdot\Ai\Registry\Drivers;
use PHPdot\Config\Configuration;
use PHPdot\Container\ContainerBuilder;
use PHPdot\Container\Testing\TestContextProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Proves the package wires through the real phpdot container: the attribute
 * services are discovered by a directory scan over the host's Configuration
 * binding, and the accessor is a singleton because it holds nothing but
 * configuration.
 */
final class ContainerTest extends TestCase
{
    #[Test]
    public function theAttributeWiringResolvesOverHostConfiguration(): void
    {
        $requests = new TestContextProvider();
        $container = (new ContainerBuilder())
            ->withContextProvider($requests)
            ->scanAttributesIn(\dirname(__DIR__) . '/src')
            ->addDefinitions([
                Configuration::class => new Configuration(__DIR__ . '/../Support/config'),
            ])
            ->build();

        $factories = $container->get(DriverFactories::class);

        self::assertInstanceOf(DriverFactories::class, $factories);

        $first = $container->get(Drivers::class);

        self::assertInstanceOf(Drivers::class, $first);
        self::assertSame($first, $container->get(Drivers::class), 'stable within one request');

        $requests->newContext('next-request');

        $next = $container->get(Drivers::class);

        self::assertInstanceOf(Drivers::class, $next, 'resolves again in the next context');
        self::assertSame(['anthropic', 'openai', 'groq'], $next->names(), 'and answers the same registry');
        self::assertInstanceOf(\PHPdot\Ai\Bridge\OpenAi\Driver::class, $next->for('groq'));
    }

    #[Test]
    public function aMissingConfigurationBindingFailsLoudly(): void
    {
        $container = (new ContainerBuilder())
            ->withContextProvider(new TestContextProvider())
            ->scanAttributesIn(\dirname(__DIR__) . '/src')
            ->build();

        try {
            $container->get(Drivers::class);

            self::fail('The accessor resolved without a Configuration binding.');
        } catch (Throwable $failure) {
            self::addToAssertionCount(1);
        }
    }
}
