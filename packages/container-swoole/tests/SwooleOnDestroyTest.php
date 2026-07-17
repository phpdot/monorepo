<?php

declare(strict_types=1);
namespace PHPdot\Container\Swoole\Tests;

use Closure;
use PHPdot\Container\ContainerBuilder;

use function PHPdot\Container\scoped;

use PHPdot\Container\Swoole\SwooleContext;
use PHPdot\Container\Swoole\SwooleContextProvider;
use PHPdot\Contracts\Container\ContextDestroyInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;

use Swoole\Coroutine;

/**
 * Verify SwooleContext implements ContextDestroyInterface and that scoped
 * onDestroy callbacks fire at coroutine end.
 */
final class SwooleOnDestroyTest extends TestCase
{
    public function testSwooleContextImplementsContextDestroyInterface(): void
    {
        self::assertTrue(is_a(SwooleContext::class, ContextDestroyInterface::class, true));
    }

    public function testOnDestroyFiresAtCoroutineEnd(): void
    {
        $result = $this->runInCoroutine(function (): array {
            $fired = [];

            // Inner coroutine — registers a destroy callback
            Coroutine::create(function () use (&$fired): void {
                $ctx = new SwooleContext();
                $ctx->onDestroy(static function () use (&$fired): void {
                    $fired[] = 'inner';
                });
                // Coroutine returns — Swoole fires deferred callbacks
            });

            // Wait briefly so the inner coroutine completes
            Coroutine::sleep(0.01);

            return $fired;
        });

        self::assertSame(['inner'], $result);
    }

    public function testScopedFactoryWithOnDestroyReleasesAtCoroutineEnd(): void
    {
        $released = $this->runInCoroutine(function (): array {
            $released = [];

            $container = (new ContainerBuilder())
                ->withContextProvider(new SwooleContextProvider())
                ->withScopeValidation(false)
                ->addDefinitions([
                    stdClass::class => scoped(
                        static fn(): stdClass => new stdClass(),
                        onDestroy: static function (object $instance, ContainerInterface $c) use (&$released): void {
                            $released[] = spl_object_id($instance);
                        },
                    ),
                ])
                ->build();

            $observed = null;

            // Inner coroutine — borrows then exits, triggering destroy
            Coroutine::create(function () use ($container, &$observed): void {
                $instance = $container->get(stdClass::class);
                $observed = spl_object_id($instance);
            });

            Coroutine::sleep(0.01);

            return ['observed' => $observed, 'released' => $released];
        });

        self::assertNotNull($result_observed = $released['observed']);
        self::assertSame([$result_observed], $released['released']);
    }

    public function testEachCoroutineGetsOwnInstanceAndOwnDestroy(): void
    {
        $result = $this->runInCoroutine(function (): array {
            $released = [];

            $container = (new ContainerBuilder())
                ->withContextProvider(new SwooleContextProvider())
                ->withScopeValidation(false)
                ->addDefinitions([
                    stdClass::class => scoped(
                        static fn(): stdClass => new stdClass(),
                        onDestroy: static function (object $instance) use (&$released): void {
                            $released[] = spl_object_id($instance);
                        },
                    ),
                ])
                ->build();

            $borrowed = [];
            $channel = new Coroutine\Channel(3);

            for ($i = 0; $i < 3; $i++) {
                Coroutine::create(function () use ($container, $channel): void {
                    $instance = $container->get(stdClass::class);
                    $channel->push(spl_object_id($instance));
                });
            }

            for ($i = 0; $i < 3; $i++) {
                $borrowed[] = $channel->pop();
            }

            Coroutine::sleep(0.01);

            return ['borrowed' => $borrowed, 'released' => $released];
        });

        // Three coroutines, three distinct instances, three destroys
        self::assertCount(3, $result['borrowed']);
        self::assertCount(3, array_unique($result['borrowed']));
        self::assertCount(3, $result['released']);
        self::assertSame(sort_copy($result['borrowed']), sort_copy($result['released']));
    }

    /**
     * @template T
     * @param Closure(): T $callback
     * @return T
     */
    private function runInCoroutine(Closure $callback): mixed
    {
        $result = null;
        Coroutine\run(static function () use ($callback, &$result): void {
            $result = $callback();
        });

        /** @var T */
        return $result;
    }
}

/**
 * @param list<int> $arr
 * @return list<int>
 */
function sort_copy(array $arr): array
{
    sort($arr);

    return $arr;
}
