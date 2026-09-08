<?php

declare(strict_types=1);

namespace PHPdot\Ai\Tests\Unit\Architecture;

use function dirname;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

use function str_replace;

/**
 * The coroutine law, walked: no mutable static state anywhere in src, and the
 * mutable instance state that exists (the SSE parser's buffer, the assembler's
 * pending calls) lives inside a turn — created per `stream()` call and discarded
 * with it, never shared between coroutines.
 */
final class CoroutineSafetyTest extends TestCase
{
    /**
     * @return iterable<string, list{class-string}>
     */
    public static function srcClasses(): iterable
    {
        $root = dirname(__DIR__, 3) . '/src';
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace([$root . '/', '.php'], '', $file->getPathname());
            $class = 'PHPdot\\Ai\\' . str_replace('/', '\\', $relative);

            if (class_exists($class) || interface_exists($class)) {
                yield $relative => [$class];
            }
        }
    }

    #[Test]
    public function noClassHoldsMutableStaticState(): void
    {
        foreach (self::srcClasses() as [$class]) {
            $reflection = new ReflectionClass($class);

            foreach ($reflection->getProperties() as $property) {
                if ($property->isStatic() && !$property->isReadOnly() && !$property->isFinal()) {
                    self::fail("{$class}::\${$property->getName()} is mutable static state");
                }
            }
        }

        $this->addToAssertionCount(1);
    }
}
