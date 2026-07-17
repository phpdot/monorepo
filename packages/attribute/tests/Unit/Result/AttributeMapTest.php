<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Unit\Result;

use PHPdot\Attribute\Enum\StructureType;
use PHPdot\Attribute\Enum\TargetType;
use PHPdot\Attribute\Result\AttributeMap;
use PHPdot\Attribute\Result\AttributeResult;
use PHPdot\Attribute\Result\ClassAttributes;
use PHPdot\Attribute\Tests\Fixtures\Attributes\Injectable;
use PHPdot\Attribute\Tests\Fixtures\Attributes\Route;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AttributeMapTest extends TestCase
{
    #[Test]
    public function getClassReturnsClassAttributes(): void
    {
        $classAttributes = $this->createClassAttributes(
            'App\\Controller\\UserController',
        );
        $map = $this->createMap(['App\\Controller\\UserController' => $classAttributes]);

        $result = $map->getClass('App\\Controller\\UserController');

        self::assertSame($classAttributes, $result);
    }

    #[Test]
    public function getClassReturnsNullForUnknownClass(): void
    {
        $map = $this->createMap([]);

        self::assertNull($map->getClass('App\\NonExistent'));
    }

    #[Test]
    public function hasClassReturnsTrueForExistingClass(): void
    {
        $classAttributes = $this->createClassAttributes(
            'App\\Controller\\UserController',
        );
        $map = $this->createMap(['App\\Controller\\UserController' => $classAttributes]);

        self::assertTrue($map->hasClass('App\\Controller\\UserController'));
    }

    #[Test]
    public function hasClassReturnsFalseForUnknownClass(): void
    {
        $map = $this->createMap([]);

        self::assertFalse($map->hasClass('App\\NonExistent'));
    }

    #[Test]
    public function countReturnsNumberOfClasses(): void
    {
        $map = $this->createMap([
            'App\\A' => $this->createClassAttributes('App\\A'),
            'App\\B' => $this->createClassAttributes('App\\B'),
            'App\\C' => $this->createClassAttributes('App\\C'),
        ]);

        self::assertSame(3, $map->count());
    }

    #[Test]
    public function countReturnsZeroForEmptyMap(): void
    {
        $map = $this->createMap([]);

        self::assertSame(0, $map->count());
    }

    #[Test]
    public function toCacheAndFromCacheRoundTrip(): void
    {
        $routeInstance = new Route('/users', ['GET'], 'users.index');
        $injectableInstance = new Injectable(singleton: true);

        $result1 = new AttributeResult(
            attribute: Route::class,
            instance: $routeInstance,
            arguments: ['/users', ['GET'], 'users.index'],
            class: 'App\\Controller\\UserController',
            target: TargetType::METHOD,
            method: 'index',
        );

        $result2 = new AttributeResult(
            attribute: Injectable::class,
            instance: $injectableInstance,
            arguments: [true],
            class: 'App\\Service\\UserService',
            target: TargetType::CLASS_TYPE,
        );

        $class1 = new ClassAttributes(
            class: 'App\\Controller\\UserController',
            structureType: StructureType::CLASS_TYPE,
            implements: ['App\\Interface\\Controller'],
            extends: null,
            results: [$result1],
        );

        $class2 = new ClassAttributes(
            class: 'App\\Service\\UserService',
            structureType: StructureType::CLASS_TYPE,
            implements: [],
            extends: 'App\\Service\\BaseService',
            results: [$result2],
        );

        $original = new AttributeMap(
            classes: [
                'App\\Controller\\UserController' => $class1,
                'App\\Service\\UserService' => $class2,
            ],
            generatedAt: 1700000000,
            directories: ['/src'],
            filter: [Route::class],
        );

        $cacheData = $original->toCache();
        $restored = AttributeMap::fromCache($cacheData);

        self::assertSame(2, $restored->count());
        self::assertTrue($restored->hasClass('App\\Controller\\UserController'));
        self::assertTrue($restored->hasClass('App\\Service\\UserService'));
        self::assertSame(1700000000, $restored->generatedAt);
        self::assertSame(['/src'], $restored->directories);
        self::assertSame([Route::class], $restored->filter);

        $restoredClass1 = $restored->getClass('App\\Controller\\UserController');
        self::assertNotNull($restoredClass1);
        self::assertSame('App\\Controller\\UserController', $restoredClass1->class);
        self::assertSame(StructureType::CLASS_TYPE, $restoredClass1->structureType);
        self::assertSame(['App\\Interface\\Controller'], $restoredClass1->implements);
        self::assertNull($restoredClass1->extends);
        self::assertCount(1, $restoredClass1->results);

        $restoredResult1 = $restoredClass1->results[0];
        self::assertSame(Route::class, $restoredResult1->attribute);
        self::assertInstanceOf(Route::class, $restoredResult1->instance);
        self::assertSame('/users', $restoredResult1->instance->path);
        self::assertSame(['GET'], $restoredResult1->instance->methods);
        self::assertSame('users.index', $restoredResult1->instance->name);
        self::assertSame(TargetType::METHOD, $restoredResult1->target);
        self::assertSame('index', $restoredResult1->method);

        $restoredClass2 = $restored->getClass('App\\Service\\UserService');
        self::assertNotNull($restoredClass2);
        self::assertSame('App\\Service\\BaseService', $restoredClass2->extends);

        $restoredResult2 = $restoredClass2->results[0];
        self::assertSame(Injectable::class, $restoredResult2->attribute);
        self::assertInstanceOf(Injectable::class, $restoredResult2->instance);
        self::assertTrue($restoredResult2->instance->singleton);
        self::assertSame(TargetType::CLASS_TYPE, $restoredResult2->target);
    }

    #[Test]
    public function getClassesReturnsAllClasses(): void
    {
        $class1 = $this->createClassAttributes('App\\A');
        $class2 = $this->createClassAttributes('App\\B');
        $map = $this->createMap([
            'App\\A' => $class1,
            'App\\B' => $class2,
        ]);

        $classes = $map->getClasses();

        self::assertCount(2, $classes);
        self::assertSame($class1, $classes['App\\A']);
        self::assertSame($class2, $classes['App\\B']);
    }

    /**
     * @param array<string, ClassAttributes> $classes
     */
    private function createMap(array $classes): AttributeMap
    {
        return new AttributeMap(
            classes: $classes,
            generatedAt: time(),
            directories: [],
            filter: [],
        );
    }

    private function createClassAttributes(string $class): ClassAttributes
    {
        return new ClassAttributes(
            class: $class,
            structureType: StructureType::CLASS_TYPE,
            implements: [],
            extends: null,
            results: [],
        );
    }
}
