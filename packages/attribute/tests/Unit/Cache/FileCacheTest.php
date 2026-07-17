<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Unit\Cache;

use PHPdot\Attribute\Cache\FileCache;
use PHPdot\Attribute\Enum\StructureType;
use PHPdot\Attribute\Enum\TargetType;
use PHPdot\Attribute\Result\AttributeMap;
use PHPdot\Attribute\Result\AttributeResult;
use PHPdot\Attribute\Result\ClassAttributes;
use PHPdot\Attribute\Tests\Fixtures\Attributes\CacheKey;
use PHPdot\Attribute\Tests\Fixtures\Attributes\Column;
use PHPdot\Attribute\Tests\Fixtures\Attributes\Injectable;
use PHPdot\Attribute\Tests\Fixtures\Attributes\Route;
use PHPdot\Attribute\Tests\Fixtures\Attributes\Validated;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FileCacheTest extends TestCase
{
    private string $cachePath;

    private FileCache $cache;

    protected function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir() . '/phpdot-attribute-test-' . uniqid() . '.php';
        $this->cache = new FileCache($this->cachePath);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->cachePath)) {
            unlink($this->cachePath);
        }
    }

    // --- write ---

    #[Test]
    public function writeCreatesCacheFile(): void
    {
        $this->cache->write($this->createSimpleMap());

        self::assertFileExists($this->cachePath);
    }

    #[Test]
    public function writeCreatesParentDirectoryIfMissing(): void
    {
        $nested = sys_get_temp_dir() . '/phpdot-test-' . uniqid() . '/sub/cache.php';
        $cache = new FileCache($nested);

        $cache->write($this->createSimpleMap());

        self::assertFileExists($nested);

        unlink($nested);
        rmdir(dirname($nested));
        rmdir(dirname($nested, 2));
    }

    #[Test]
    public function writeProducesValidPhp(): void
    {
        $this->cache->write($this->createSimpleMap());

        $content = file_get_contents($this->cachePath);
        self::assertIsString($content);
        self::assertStringStartsWith('<?php', $content);
        self::assertStringContainsString('declare(strict_types=1)', $content);
    }

    // --- has ---

    #[Test]
    public function hasReturnsFalseWhenCacheDoesNotExist(): void
    {
        self::assertFalse($this->cache->has());
    }

    #[Test]
    public function hasReturnsTrueAfterWrite(): void
    {
        $this->cache->write($this->createSimpleMap());

        self::assertTrue($this->cache->has());
    }

    // --- read ---

    #[Test]
    public function readReturnsNullWhenFileDoesNotExist(): void
    {
        self::assertNull($this->cache->read());
    }

    #[Test]
    public function readReturnsAttributeMap(): void
    {
        $this->cache->write($this->createSimpleMap());

        $result = $this->cache->read();

        self::assertNotNull($result);
        self::assertInstanceOf(AttributeMap::class, $result);
    }

    // --- clear ---

    #[Test]
    public function clearDeletesCacheFile(): void
    {
        $this->cache->write($this->createSimpleMap());
        self::assertTrue($this->cache->has());

        $this->cache->clear();

        self::assertFalse($this->cache->has());
        self::assertFileDoesNotExist($this->cachePath);
    }

    #[Test]
    public function clearDoesNothingWhenFileDoesNotExist(): void
    {
        $this->cache->clear();

        self::assertFalse($this->cache->has());
    }

    // --- Round-trip: simple ---

    #[Test]
    public function roundTripPreservesMetadata(): void
    {
        $original = $this->createSimpleMap();
        $this->cache->write($original);

        $restored = $this->cache->read();

        self::assertNotNull($restored);
        self::assertSame($original->generatedAt, $restored->generatedAt);
        self::assertSame($original->directories, $restored->directories);
        self::assertSame($original->filter, $restored->filter);
        self::assertSame($original->count(), $restored->count());
    }

    #[Test]
    public function roundTripPreservesClassAttributes(): void
    {
        $original = $this->createSimpleMap();
        $this->cache->write($original);

        $restored = $this->cache->read();
        self::assertNotNull($restored);

        self::assertTrue($restored->hasClass('App\\UserController'));

        $class = $restored->getClass('App\\UserController');
        self::assertNotNull($class);
        self::assertSame('App\\UserController', $class->class);
        self::assertSame(StructureType::CLASS_TYPE, $class->structureType);
        self::assertSame([], $class->implements);
        self::assertNull($class->extends);
    }

    #[Test]
    public function roundTripPreservesAttributeInstances(): void
    {
        $original = $this->createSimpleMap();
        $this->cache->write($original);

        $restored = $this->cache->read();
        self::assertNotNull($restored);

        $class = $restored->getClass('App\\UserController');
        self::assertNotNull($class);

        $route = $class->results[0];
        self::assertSame(Route::class, $route->attribute);
        self::assertInstanceOf(Route::class, $route->instance);
        self::assertSame('/users', $route->instance->path);
        self::assertSame(['GET'], $route->instance->methods);
    }

    // --- Round-trip: all target types ---

    #[Test]
    public function roundTripPreservesMethodTarget(): void
    {
        $map = $this->createAllTargetsMap();
        $this->cache->write($map);

        $restored = $this->cache->read();
        self::assertNotNull($restored);

        $class = $restored->getClass('App\\FullFixture');
        self::assertNotNull($class);

        $methods = $class->methodAttributes();
        self::assertCount(1, $methods);
        self::assertSame('index', $methods[0]->method);
        self::assertSame(TargetType::METHOD, $methods[0]->target);
    }

    #[Test]
    public function roundTripPreservesPropertyTarget(): void
    {
        $map = $this->createAllTargetsMap();
        $this->cache->write($map);

        $restored = $this->cache->read();
        self::assertNotNull($restored);

        $class = $restored->getClass('App\\FullFixture');
        self::assertNotNull($class);

        $props = $class->propertyAttributes();
        self::assertCount(1, $props);
        self::assertSame('name', $props[0]->property);
        self::assertSame(TargetType::PROPERTY, $props[0]->target);
        self::assertInstanceOf(Column::class, $props[0]->instance);
    }

    #[Test]
    public function roundTripPreservesParameterTarget(): void
    {
        $map = $this->createAllTargetsMap();
        $this->cache->write($map);

        $restored = $this->cache->read();
        self::assertNotNull($restored);

        $class = $restored->getClass('App\\FullFixture');
        self::assertNotNull($class);

        $params = $class->parameterAttributes();
        self::assertCount(1, $params);
        self::assertSame('data', $params[0]->parameter);
        self::assertSame('store', $params[0]->method);
        self::assertSame(TargetType::PARAMETER, $params[0]->target);
    }

    #[Test]
    public function roundTripPreservesConstantTarget(): void
    {
        $map = $this->createAllTargetsMap();
        $this->cache->write($map);

        $restored = $this->cache->read();
        self::assertNotNull($restored);

        $class = $restored->getClass('App\\FullFixture');
        self::assertNotNull($class);

        $constants = $class->constantAttributes();
        self::assertCount(1, $constants);
        self::assertSame('VERSION', $constants[0]->constant);
        self::assertSame(TargetType::CONSTANT, $constants[0]->target);
        self::assertInstanceOf(CacheKey::class, $constants[0]->instance);
    }

    // --- Round-trip: all structure types ---

    #[Test]
    public function roundTripPreservesEnumStructureType(): void
    {
        $map = $this->createAllStructureTypesMap();
        $this->cache->write($map);

        $restored = $this->cache->read();
        self::assertNotNull($restored);

        $class = $restored->getClass('App\\MyEnum');
        self::assertNotNull($class);
        self::assertSame(StructureType::ENUM_TYPE, $class->structureType);
    }

    #[Test]
    public function roundTripPreservesInterfaceStructureType(): void
    {
        $map = $this->createAllStructureTypesMap();
        $this->cache->write($map);

        $restored = $this->cache->read();
        self::assertNotNull($restored);

        $class = $restored->getClass('App\\MyInterface');
        self::assertNotNull($class);
        self::assertSame(StructureType::INTERFACE_TYPE, $class->structureType);
    }

    #[Test]
    public function roundTripPreservesTraitStructureType(): void
    {
        $map = $this->createAllStructureTypesMap();
        $this->cache->write($map);

        $restored = $this->cache->read();
        self::assertNotNull($restored);

        $class = $restored->getClass('App\\MyTrait');
        self::assertNotNull($class);
        self::assertSame(StructureType::TRAIT_TYPE, $class->structureType);
    }

    // --- Round-trip: extends/implements ---

    #[Test]
    public function roundTripPreservesExtendsAndImplements(): void
    {
        $map = new AttributeMap(
            classes: [
                'App\\Child' => new ClassAttributes(
                    class: 'App\\Child',
                    structureType: StructureType::CLASS_TYPE,
                    implements: ['App\\FooInterface', 'App\\BarInterface'],
                    extends: 'App\\BaseClass',
                    results: [new AttributeResult(
                        attribute: Injectable::class,
                        instance: new Injectable(),
                        arguments: [],
                        class: 'App\\Child',
                        target: TargetType::CLASS_TYPE,
                    )],
                ),
            ],
            generatedAt: 1700000000,
            directories: ['/src'],
            filter: [],
        );

        $this->cache->write($map);
        $restored = $this->cache->read();
        self::assertNotNull($restored);

        $child = $restored->getClass('App\\Child');
        self::assertNotNull($child);
        self::assertSame('App\\BaseClass', $child->extends);
        self::assertSame(['App\\FooInterface', 'App\\BarInterface'], $child->implements);
    }

    // --- Round-trip: complex arguments ---

    #[Test]
    public function roundTripPreservesNestedArrayArguments(): void
    {
        $map = new AttributeMap(
            classes: [
                'App\\X' => new ClassAttributes(
                    class: 'App\\X',
                    structureType: StructureType::CLASS_TYPE,
                    implements: [],
                    extends: null,
                    results: [new AttributeResult(
                        attribute: Route::class,
                        instance: new Route('/test', ['GET', 'POST'], 'test.route'),
                        arguments: ['/test', ['GET', 'POST'], 'test.route'],
                        class: 'App\\X',
                        target: TargetType::METHOD,
                        method: 'test',
                    )],
                ),
            ],
            generatedAt: time(),
            directories: [],
            filter: [],
        );

        $this->cache->write($map);
        $restored = $this->cache->read();
        self::assertNotNull($restored);

        $class = $restored->getClass('App\\X');
        self::assertNotNull($class);
        $result = $class->results[0];
        self::assertInstanceOf(Route::class, $result->instance);
        self::assertSame(['GET', 'POST'], $result->instance->methods);
        self::assertSame('test.route', $result->instance->name);
    }

    private function createSimpleMap(): AttributeMap
    {
        return new AttributeMap(
            classes: [
                'App\\UserController' => new ClassAttributes(
                    class: 'App\\UserController',
                    structureType: StructureType::CLASS_TYPE,
                    implements: [],
                    extends: null,
                    results: [
                        new AttributeResult(
                            attribute: Route::class,
                            instance: new Route('/users', ['GET'], 'users.index'),
                            arguments: ['/users', ['GET'], 'users.index'],
                            class: 'App\\UserController',
                            target: TargetType::METHOD,
                            method: 'index',
                        ),
                        new AttributeResult(
                            attribute: Injectable::class,
                            instance: new Injectable(singleton: true),
                            arguments: [true],
                            class: 'App\\UserController',
                            target: TargetType::CLASS_TYPE,
                        ),
                    ],
                ),
            ],
            generatedAt: 1700000000,
            directories: ['/src'],
            filter: [Route::class],
        );
    }

    private function createAllTargetsMap(): AttributeMap
    {
        return new AttributeMap(
            classes: [
                'App\\FullFixture' => new ClassAttributes(
                    class: 'App\\FullFixture',
                    structureType: StructureType::CLASS_TYPE,
                    implements: [],
                    extends: null,
                    results: [
                        new AttributeResult(
                            attribute: Injectable::class,
                            instance: new Injectable(),
                            arguments: [],
                            class: 'App\\FullFixture',
                            target: TargetType::CLASS_TYPE,
                        ),
                        new AttributeResult(
                            attribute: Route::class,
                            instance: new Route('/test'),
                            arguments: ['/test'],
                            class: 'App\\FullFixture',
                            target: TargetType::METHOD,
                            method: 'index',
                        ),
                        new AttributeResult(
                            attribute: Column::class,
                            instance: new Column('col_name'),
                            arguments: ['col_name'],
                            class: 'App\\FullFixture',
                            target: TargetType::PROPERTY,
                            property: 'name',
                        ),
                        new AttributeResult(
                            attribute: Validated::class,
                            instance: new Validated(),
                            arguments: [],
                            class: 'App\\FullFixture',
                            target: TargetType::PARAMETER,
                            method: 'store',
                            parameter: 'data',
                        ),
                        new AttributeResult(
                            attribute: CacheKey::class,
                            instance: new CacheKey(prefix: 'v1'),
                            arguments: ['v1'],
                            class: 'App\\FullFixture',
                            target: TargetType::CONSTANT,
                            constant: 'VERSION',
                        ),
                    ],
                ),
            ],
            generatedAt: time(),
            directories: [],
            filter: [],
        );
    }

    private function createAllStructureTypesMap(): AttributeMap
    {
        $makeResult = static fn(string $class) => [new AttributeResult(
            attribute: Injectable::class,
            instance: new Injectable(),
            arguments: [],
            class: $class,
            target: TargetType::CLASS_TYPE,
        )];

        return new AttributeMap(
            classes: [
                'App\\MyClass' => new ClassAttributes(
                    class: 'App\\MyClass',
                    structureType: StructureType::CLASS_TYPE,
                    implements: [],
                    extends: null,
                    results: $makeResult('App\\MyClass'),
                ),
                'App\\MyEnum' => new ClassAttributes(
                    class: 'App\\MyEnum',
                    structureType: StructureType::ENUM_TYPE,
                    implements: [],
                    extends: null,
                    results: $makeResult('App\\MyEnum'),
                ),
                'App\\MyInterface' => new ClassAttributes(
                    class: 'App\\MyInterface',
                    structureType: StructureType::INTERFACE_TYPE,
                    implements: [],
                    extends: null,
                    results: $makeResult('App\\MyInterface'),
                ),
                'App\\MyTrait' => new ClassAttributes(
                    class: 'App\\MyTrait',
                    structureType: StructureType::TRAIT_TYPE,
                    implements: [],
                    extends: null,
                    results: $makeResult('App\\MyTrait'),
                ),
            ],
            generatedAt: time(),
            directories: [],
            filter: [],
        );
    }
}
