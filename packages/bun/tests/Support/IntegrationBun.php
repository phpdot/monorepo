<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Support;

use PHPdot\Bun\Bun;
use PHPdot\Bun\Config\BunConfig;
use PHPdot\Bun\Http\HttpClient;
use PHPdot\Bun\Process\BunProcess;
use PHPdot\Bun\Process\ProcessRunnerInterface;
use PHPdot\Container\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use RuntimeException;

/**
 * Builds a real Bun service for the integration tests. The #[Singleton] attribute scan autowires the
 * whole dependency graph; the only thing supplied here is the three interface bindings the #[Binds]
 * attributes declare (a real app gets these from phpdot/package's generated definitions) plus the
 * test's BunConfig. No hand-assembled graph, no service provider.
 */
final class IntegrationBun
{
    public static function create(?string $runtimeDir = null, ?string $workingDir = null): Bun
    {
        $container = (new ContainerBuilder())
            ->scanAttributesIn(dirname(__DIR__, 2) . '/src')
            ->addDefinitions([
                ClientInterface::class => static fn(ContainerInterface $c) => $c->get(HttpClient::class),
                RequestFactoryInterface::class => static fn(ContainerInterface $c) => $c->get(HttpClient::class),
                ProcessRunnerInterface::class => static fn(ContainerInterface $c) => $c->get(BunProcess::class),
                BunConfig::class => static fn(): BunConfig => new BunConfig(
                    runtimeDir: $runtimeDir ?? sys_get_temp_dir() . '/phpdot-bun-it-runtime',
                    workingDir: $workingDir,
                ),
            ])
            ->build();

        $bun = $container->get(Bun::class);
        if (! $bun instanceof Bun) {
            throw new RuntimeException('Bun could not be resolved from the container');
        }

        return $bun;
    }
}
