<?php

declare(strict_types=1);
namespace PHPdot\Container\Swoole\Tests;

use Closure;
use PHPdot\Container\Context\ArrayContext;
use PHPdot\Container\Swoole\SwooleContext;
use PHPdot\Container\Swoole\SwooleContextProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Swoole\Coroutine;

final class SwooleContextTest extends TestCase
{
    // ─── SwooleContext basic operations ───

    public function testSetAndGetInsideCoroutine(): void
    {
        $result = $this->runInCoroutine(function (): bool {
            $ctx = new SwooleContext();
            $obj = new stdClass();

            $ctx->set('key', $obj);

            return $ctx->has('key') && $ctx->get('key') === $obj;
        });

        $this->assertTrue($result);
    }

    public function testGetReturnsNullForMissing(): void
    {
        $result = $this->runInCoroutine(function (): bool {
            $ctx = new SwooleContext();

            return $ctx->get('missing') === null && !$ctx->has('missing');
        });

        $this->assertTrue($result);
    }

    public function testUnset(): void
    {
        $result = $this->runInCoroutine(function (): bool {
            $ctx = new SwooleContext();
            $ctx->set('key', new stdClass());
            $ctx->unset('key');

            return !$ctx->has('key');
        });

        $this->assertTrue($result);
    }

    public function testReset(): void
    {
        $result = $this->runInCoroutine(function (): bool {
            $ctx = new SwooleContext();
            $ctx->set('a', new stdClass());
            $ctx->set('b', new stdClass());
            $ctx->reset();

            return !$ctx->has('a') && !$ctx->has('b');
        });

        $this->assertTrue($result);
    }

    // ─── Coroutine isolation ───

    public function testDifferentCoroutinesAreIsolated(): void
    {
        $result = $this->runInCoroutine(function (): bool {
            $channel = new Coroutine\Channel(2);

            // Coroutine 1: set a value
            Coroutine::create(function () use ($channel): void {
                $ctx = new SwooleContext();
                $obj = new stdClass();
                $obj->id = 'coroutine-1';
                $ctx->set('user', $obj);

                // Wait so coroutine 2 runs
                Coroutine::sleep(0.01);

                // Verify our value is still ours
                /** @var stdClass|null $found */
                $found = $ctx->get('user');
                $channel->push($found !== null && $found->id === 'coroutine-1');
            });

            // Coroutine 2: set a different value
            Coroutine::create(function () use ($channel): void {
                $ctx = new SwooleContext();
                $obj = new stdClass();
                $obj->id = 'coroutine-2';
                $ctx->set('user', $obj);

                /** @var stdClass|null $found */
                $found = $ctx->get('user');
                $channel->push($found !== null && $found->id === 'coroutine-2');
            });

            $result1 = $channel->pop(1.0);
            $result2 = $channel->pop(1.0);

            return $result1 === true && $result2 === true;
        });

        $this->assertTrue($result);
    }

    public function testCoroutineContextNotVisibleFromAnother(): void
    {
        $result = $this->runInCoroutine(function (): bool {
            $channel = new Coroutine\Channel(1);

            Coroutine::create(function (): void {
                $ctx = new SwooleContext();
                $ctx->set('secret', new stdClass());
            });

            // Let coroutine 1 finish
            Coroutine::sleep(0.01);

            Coroutine::create(function () use ($channel): void {
                $ctx = new SwooleContext();
                // Should NOT see coroutine 1's data
                $channel->push(!$ctx->has('secret'));
            });

            return $channel->pop(1.0) === true;
        });

        $this->assertTrue($result);
    }

    // ─── SwooleContextProvider ───

    public function testProviderReturnsSwooleContextInsideCoroutine(): void
    {
        $result = $this->runInCoroutine(function (): bool {
            $provider = new SwooleContextProvider();
            $ctx = $provider->getContext();

            return $ctx instanceof SwooleContext;
        });

        $this->assertTrue($result);
    }

    public function testProviderReturnsFallbackOutsideCoroutine(): void
    {
        $provider = new SwooleContextProvider();
        $ctx = $provider->getContext();

        $this->assertInstanceOf(ArrayContext::class, $ctx);
    }

    public function testProviderFallbackIsSameInstance(): void
    {
        $provider = new SwooleContextProvider();
        $a = $provider->getContext();
        $b = $provider->getContext();

        $this->assertSame($a, $b);
    }

    // ─── Helper ───

    /**
     * Run a closure inside a Swoole coroutine and return the result.
     *
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
