<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Unit\Architecture;

use function file_get_contents;

use FilesystemIterator;

use function implode;

use PHPdot\Container\Attribute\Scoped;
use PHPdot\Container\Attribute\Singleton;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function realpath;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionProperty;

use function str_replace;
use function strlen;
use function substr;

/**
 * Structural coroutine-safety guarantees for the package.
 *
 * Under a persistent runtime (Swoole) a worker process lives forever and serves
 * many requests as concurrent coroutines. A service that holds request state and
 * is shared across coroutines leaks one request's data into another — the exact
 * bug class we must rule out. These tests make that impossible by construction
 * and assert it so it can never silently regress:
 *
 *   1. No mutable static state anywhere — a static property is process-global,
 *      shared by every coroutine.
 *   2. No superglobal reads — request data must be injected, not pulled from a
 *      process-global ($_GET/$_POST/$_SESSION/…), which Swoole does not populate.
 *   3. Any class that holds mutable instance state must be #[Scoped] (a fresh
 *      instance per coroutine) and never #[Singleton] (shared across coroutines).
 *      Stateless classes may be anything; value objects are readonly.
 */
final class CoroutineSafetyTest extends TestCase
{
    private const string SRC = __DIR__ . '/../../../src';

    /**
     * Every concrete + abstract class, interface, enum and trait under src/.
     *
     * @return iterable<string, array{class-string}>
     */
    public static function sourceClasses(): iterable
    {
        $dir = (string) realpath(self::SRC);

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($dir) + 1);
            /** @var class-string $fqcn */
            $fqcn = 'PHPdot\\Iam\\' . str_replace(['/', '.php'], ['\\', ''], $relative);

            yield $fqcn => [$fqcn];
        }
    }

    #[DataProvider('sourceClasses')]
    #[Test]
    public function noMutableStaticState(string $class): void
    {
        $reflection = new ReflectionClass($class);

        $statics = [];
        foreach ($reflection->getProperties(ReflectionProperty::IS_STATIC) as $property) {
            if ($property->getDeclaringClass()->getName() === $class) {
                $statics[] = $property->getName();
            }
        }

        self::assertSame(
            [],
            $statics,
            $class . ' declares mutable static state (' . implode(', ', $statics) . '): static properties are shared by every coroutine.',
        );
    }

    #[DataProvider('sourceClasses')]
    #[Test]
    public function statefulClassesAreScoped(string $class): void
    {
        $reflection = new ReflectionClass($class);

        if ($reflection->isInterface() || $reflection->isEnum() || $reflection->isAbstract()) {
            self::assertTrue(true);

            return;
        }

        $mutable = [];
        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic() || $property->getDeclaringClass()->getName() !== $class) {
                continue;
            }

            if (! $property->isReadOnly()) {
                $mutable[] = $property->getName();
            }
        }

        if ($mutable === []) {
            self::assertTrue(true);

            return;
        }

        self::assertNotEmpty(
            $reflection->getAttributes(Scoped::class),
            $class . ' holds mutable instance state (' . implode(', ', $mutable) . ') but is not #[Scoped] — it would leak across coroutines.',
        );

        self::assertEmpty(
            $reflection->getAttributes(Singleton::class),
            $class . ' holds mutable instance state but is #[Singleton] — that instance is shared across coroutines.',
        );
    }

    #[Test]
    public function noSuperglobalReads(): void
    {
        $dir = (string) realpath(self::SRC);

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        );

        $offenders = [];
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $code = (string) file_get_contents($file->getPathname());

            if (preg_match('/\$_(GET|POST|REQUEST|SESSION|COOKIE|SERVER|FILES|ENV)\b|\$GLOBALS\b/', $code) === 1) {
                $offenders[] = substr($file->getPathname(), strlen($dir) + 1);
            }
        }

        self::assertSame(
            [],
            $offenders,
            'Superglobal reads found (request data must be injected, not pulled from process globals): ' . implode(', ', $offenders),
        );
    }
}
