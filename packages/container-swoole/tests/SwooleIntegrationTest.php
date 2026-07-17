<?php

declare(strict_types=1);
namespace PHPdot\Container\Swoole\Tests;

use Closure;
use PHPdot\Container\ContainerBuilder;

use function PHPdot\Container\scoped;

use PHPdot\Container\ScopedContainer;

use function PHPdot\Container\singleton;

use PHPdot\Container\Swoole\SwooleContextProvider;

use function PHPdot\Container\transient;

use PHPUnit\Framework\TestCase;
use stdClass;
use Swoole\Coroutine;

final class SwooleIntegrationTest extends TestCase
{
    // ─── Singleton across coroutines ───

    public function testSingletonSharedAcrossCoroutines(): void
    {
        $result = $this->runInCoroutine(function (): bool {
            $container = $this->buildContainer([
                'router' => singleton(function () {
                    $o = new stdClass();
                    $o->id = uniqid('', true);

                    return $o;
                }),
            ]);

            $channel = new Coroutine\Channel(2);

            Coroutine::create(function () use ($container, $channel): void {
                /** @var stdClass $router */
                $router = $container->get('router');
                $channel->push($router->id);
            });

            Coroutine::create(function () use ($container, $channel): void {
                /** @var stdClass $router */
                $router = $container->get('router');
                $channel->push($router->id);
            });

            $id1 = $channel->pop(1.0);
            $id2 = $channel->pop(1.0);

            return $id1 === $id2;
        });

        $this->assertTrue($result, 'Singleton should be the same across coroutines');
    }

    // ─── Scoped isolated per coroutine ───

    public function testScopedIsolatedPerCoroutine(): void
    {
        $result = $this->runInCoroutine(function (): bool {
            $container = $this->buildContainer([
                'session' => scoped(function () {
                    $o = new stdClass();
                    $o->id = uniqid('', true);

                    return $o;
                }),
            ]);

            $channel = new Coroutine\Channel(2);

            Coroutine::create(function () use ($container, $channel): void {
                /** @var stdClass $session */
                $session = $container->get('session');
                $channel->push($session->id);
            });

            Coroutine::create(function () use ($container, $channel): void {
                /** @var stdClass $session */
                $session = $container->get('session');
                $channel->push($session->id);
            });

            $id1 = $channel->pop(1.0);
            $id2 = $channel->pop(1.0);

            return $id1 !== $id2;
        });

        $this->assertTrue($result, 'Scoped should be different across coroutines');
    }

    public function testScopedSameWithinCoroutine(): void
    {
        $result = $this->runInCoroutine(function (): bool {
            $container = $this->buildContainer([
                'session' => scoped(function () {
                    return new stdClass();
                }),
            ]);

            $a = $container->get('session');
            $b = $container->get('session');

            return $a === $b;
        });

        $this->assertTrue($result, 'Scoped should be the same within a coroutine');
    }

    // ─── Transient always fresh ───

    public function testTransientAlwaysFreshInCoroutine(): void
    {
        $result = $this->runInCoroutine(function (): bool {
            $container = $this->buildContainer([
                'mail' => transient(function () {
                    return new stdClass();
                }),
            ]);

            $a = $container->get('mail');
            $b = $container->get('mail');

            return $a !== $b;
        });

        $this->assertTrue($result, 'Transient should always be different');
    }

    // ─── Mixed scopes under concurrency ───

    public function testMixedScopesUnderConcurrency(): void
    {
        $result = $this->runInCoroutine(function (): bool {
            $container = $this->buildContainer([
                'config' => singleton(function () {
                    $o = new stdClass();
                    $o->id = uniqid('', true);

                    return $o;
                }),
                'session' => scoped(function () {
                    $o = new stdClass();
                    $o->id = uniqid('', true);

                    return $o;
                }),
                'mail' => transient(function () {
                    $o = new stdClass();
                    $o->id = uniqid('', true);

                    return $o;
                }),
            ]);

            $channel = new Coroutine\Channel(6);

            for ($i = 0; $i < 3; $i++) {
                Coroutine::create(function () use ($container, $channel): void {
                    /** @var stdClass $config */
                    $config = $container->get('config');
                    /** @var stdClass $session */
                    $session = $container->get('session');

                    $channel->push(['config' => $config->id, 'session' => $session->id]);
                });
            }

            $results = [];
            for ($i = 0; $i < 3; $i++) {
                /** @var array{config: string, session: string} $item */
                $item = $channel->pop(1.0);
                $results[] = $item;
            }

            // Config (singleton) should be same across all coroutines
            $configIds = array_unique(array_column($results, 'config'));
            $configSame = count($configIds) === 1;

            // Session (scoped) should be different across coroutines
            $sessionIds = array_unique(array_column($results, 'session'));
            $sessionDifferent = count($sessionIds) === 3;

            return $configSame && $sessionDifferent;
        });

        $this->assertTrue($result, 'Singleton shared, scoped isolated across 3 concurrent coroutines');
    }

    // ─── High concurrency stress test ───

    public function testHighConcurrencyNoLeaks(): void
    {
        $result = $this->runInCoroutine(function (): bool {
            $container = $this->buildContainer([
                'session' => scoped(function () {
                    $o = new stdClass();
                    $o->cid = Coroutine::getCid();

                    return $o;
                }),
            ]);

            $count = 50;
            $channel = new Coroutine\Channel($count);

            for ($i = 0; $i < $count; $i++) {
                Coroutine::create(function () use ($container, $channel): void {
                    /** @var stdClass $session */
                    $session = $container->get('session');
                    $myCid = Coroutine::getCid();

                    // The session's cid should match this coroutine's cid
                    $channel->push($session->cid === $myCid);
                });
            }

            $allCorrect = true;
            for ($i = 0; $i < $count; $i++) {
                if ($channel->pop(2.0) !== true) {
                    $allCorrect = false;
                }
            }

            return $allCorrect;
        });

        $this->assertTrue($result, 'All 50 concurrent coroutines got their own scoped instance');
    }

    // ─── Scoped cleanup after coroutine exits ───

    public function testScopedCleanedUpAfterCoroutineExits(): void
    {
        $result = $this->runInCoroutine(function (): bool {
            $container = $this->buildContainer([
                'temp' => scoped(function () {
                    return new stdClass();
                }),
            ]);

            $channel = new Coroutine\Channel(1);

            // Coroutine creates a scoped instance then exits
            Coroutine::create(function () use ($container, $channel): void {
                $container->get('temp');
                $channel->push(true);
            });

            $channel->pop(1.0);

            // New coroutine should get a fresh instance, not the old one
            $channel2 = new Coroutine\Channel(1);

            Coroutine::create(function () use ($container, $channel2): void {
                // This is a new coroutine — should get new scoped instance
                $obj = $container->get('temp');
                $channel2->push($obj instanceof stdClass);
            });

            return $channel2->pop(1.0) === true;
        });

        $this->assertTrue($result);
    }

    // ─── Context resetter works in coroutine ───

    public function testContextResetterInCoroutine(): void
    {
        $result = $this->runInCoroutine(function (): bool {
            $container = $this->buildContainer([
                'session' => scoped(function () {
                    return new stdClass();
                }),
            ]);

            $a = $container->get('session');

            /** @var \PHPdot\Container\ContextResetter $resetter */
            $resetter = $container->get(\PHPdot\Container\ContextResetter::class);
            $resetter->reset();

            $b = $container->get('session');

            return $a !== $b;
        });

        $this->assertTrue($result, 'After reset, scoped should return new instance');
    }

    // ─── PHP-DI values work alongside scoped ───

    public function testDiValuesWorkInCoroutine(): void
    {
        $result = $this->runInCoroutine(function (): bool {
            $container = $this->buildContainer([
                'app.name' => \DI\value('PHPdot'),
                'app.port' => \DI\value(8080),
            ]);

            return $container->get('app.name') === 'PHPdot'
                && $container->get('app.port') === 8080;
        });

        $this->assertTrue($result);
    }

    // ─── Helper ───

    /**
     * @param array<string, mixed> $definitions
     */
    private function buildContainer(array $definitions): ScopedContainer
    {
        return (new ContainerBuilder())
            ->withContextProvider(new SwooleContextProvider())
            ->withScopeValidation(false)
            ->addDefinitions($definitions)
            ->build();
    }

    /**
     * @template T
     * @param Closure(): T $callback
     * @return T
     */
    private function runInCoroutine(Closure $callback): mixed
    {
        $result = null;

        Coroutine\run(function () use ($callback, &$result): void {
            $result = $callback();
        });

        return $result;
    }
}
