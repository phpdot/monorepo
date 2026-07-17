<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Unit;

use PHPdot\Attribute\Enum\StructureType;
use PHPdot\Attribute\Enum\TargetType;
use PHPdot\Attribute\ReflectionScanner;
use PHPdot\Attribute\Result\AttributeMap;
use PHPdot\Attribute\Tests\Fixtures\Attributes\Broken;
use PHPdot\Attribute\Tests\Fixtures\Attributes\CacheKey;
use PHPdot\Attribute\Tests\Fixtures\Attributes\Column;
use PHPdot\Attribute\Tests\Fixtures\Attributes\Injectable;
use PHPdot\Attribute\Tests\Fixtures\Attributes\Middleware;
use PHPdot\Attribute\Tests\Fixtures\Attributes\Route;
use PHPdot\Attribute\Tests\Fixtures\Attributes\Validated;
use PHPdot\Attribute\Tests\Fixtures\Classes\AnnotatedController;
use PHPdot\Attribute\Tests\Fixtures\Classes\AnnotatedEnum;
use PHPdot\Attribute\Tests\Fixtures\Classes\AnnotatedInterface;
use PHPdot\Attribute\Tests\Fixtures\Classes\AnnotatedService;
use PHPdot\Attribute\Tests\Fixtures\Classes\AnnotatedTrait;
use PHPdot\Attribute\Tests\Fixtures\Classes\BaseController;
use PHPdot\Attribute\Tests\Fixtures\Classes\BrokenAttributeFixture;
use PHPdot\Attribute\Tests\Fixtures\Classes\ChildController;
use PHPdot\Attribute\Tests\Fixtures\Classes\ConstantFixture;
use PHPdot\Attribute\Tests\Fixtures\Classes\PlainClass;
use PHPdot\Attribute\Tests\Fixtures\Classes\VisibilityFixture;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ReflectionScannerTest extends TestCase
{
    private ReflectionScanner $scanner;

    protected function setUp(): void
    {
        $this->scanner = new ReflectionScanner();
    }

    // --- Return type ---

    #[Test]
    public function scanReturnsAttributeMap(): void
    {
        $map = $this->scanner->scan([AnnotatedController::class]);

        self::assertInstanceOf(AttributeMap::class, $map);
    }

    #[Test]
    public function scanSetsDirectoriesOnMap(): void
    {
        $map = $this->scanner->scan([AnnotatedController::class], directories: ['/src']);

        self::assertSame(['/src'], $map->directories);
    }

    #[Test]
    public function scanSetsFilterOnMap(): void
    {
        $map = $this->scanner->scan([AnnotatedController::class], filter: [Route::class]);

        self::assertSame([Route::class], $map->filter);
    }

    #[Test]
    public function scanSetsGeneratedAt(): void
    {
        $before = time();
        $map = $this->scanner->scan([AnnotatedController::class]);

        self::assertGreaterThanOrEqual($before, $map->generatedAt);
    }

    // --- Class-level attributes ---

    #[Test]
    public function scansClassLevelAttributes(): void
    {
        $map = $this->scanner->scan([AnnotatedController::class]);
        $classAttrs = $map->getClass(AnnotatedController::class);

        self::assertNotNull($classAttrs);

        $classLevel = $classAttrs->classAttributes();
        self::assertCount(2, $classLevel);

        $names = array_map(static fn($r) => $r->attribute, $classLevel);
        self::assertContains(Route::class, $names);
        self::assertContains(Middleware::class, $names);
    }

    #[Test]
    public function classLevelAttributeInstancesAreCorrect(): void
    {
        $map = $this->scanner->scan([AnnotatedController::class]);
        $classAttrs = $map->getClass(AnnotatedController::class);
        self::assertNotNull($classAttrs);

        $route = $classAttrs->get(Route::class);
        self::assertNotNull($route);
        self::assertInstanceOf(Route::class, $route->instance);
        self::assertSame('/users', $route->instance->path);

        $middleware = $classAttrs->get(Middleware::class);
        self::assertNotNull($middleware);
        self::assertInstanceOf(Middleware::class, $middleware->instance);
        self::assertSame('auth', $middleware->instance->name);
    }

    #[Test]
    public function classLevelAttributeTargetIsClassType(): void
    {
        $map = $this->scanner->scan([AnnotatedController::class]);
        $classAttrs = $map->getClass(AnnotatedController::class);
        self::assertNotNull($classAttrs);

        foreach ($classAttrs->classAttributes() as $attr) {
            self::assertSame(TargetType::CLASS_TYPE, $attr->target);
            self::assertSame(AnnotatedController::class, $attr->class);
            self::assertNull($attr->method);
            self::assertNull($attr->property);
            self::assertNull($attr->parameter);
            self::assertNull($attr->constant);
        }
    }

    // --- Method-level attributes ---

    #[Test]
    public function scansMethodLevelAttributes(): void
    {
        $map = $this->scanner->scan([AnnotatedController::class]);
        $classAttrs = $map->getClass(AnnotatedController::class);
        self::assertNotNull($classAttrs);

        $methodAttrs = $classAttrs->methodAttributes();
        self::assertCount(3, $methodAttrs);

        $methods = array_map(static fn($r) => $r->method, $methodAttrs);
        self::assertContains('index', $methods);
        self::assertContains('show', $methods);
        self::assertContains('store', $methods);
    }

    #[Test]
    public function methodAttributeTargetIsMethod(): void
    {
        $map = $this->scanner->scan([AnnotatedController::class]);
        $classAttrs = $map->getClass(AnnotatedController::class);
        self::assertNotNull($classAttrs);

        foreach ($classAttrs->methodAttributes() as $attr) {
            self::assertSame(TargetType::METHOD, $attr->target);
            self::assertNotNull($attr->method);
        }
    }

    #[Test]
    public function methodAttributeInstanceHasCorrectArguments(): void
    {
        $map = $this->scanner->scan([AnnotatedController::class]);
        $classAttrs = $map->getClass(AnnotatedController::class);
        self::assertNotNull($classAttrs);

        $indexAttrs = $classAttrs->methodAttributes('index');
        self::assertCount(1, $indexAttrs);
        self::assertInstanceOf(Route::class, $indexAttrs[0]->instance);
        self::assertSame('/users', $indexAttrs[0]->instance->path);
        self::assertSame(['GET'], $indexAttrs[0]->instance->methods);
        self::assertSame('users.index', $indexAttrs[0]->instance->name);
    }

    // --- Parameter-level attributes ---

    #[Test]
    public function scansParameterLevelAttributes(): void
    {
        $map = $this->scanner->scan([AnnotatedController::class]);
        $classAttrs = $map->getClass(AnnotatedController::class);
        self::assertNotNull($classAttrs);

        $paramAttrs = $classAttrs->parameterAttributes('store');
        self::assertCount(1, $paramAttrs);
        self::assertSame(Validated::class, $paramAttrs[0]->attribute);
        self::assertSame('data', $paramAttrs[0]->parameter);
        self::assertSame('store', $paramAttrs[0]->method);
        self::assertSame(TargetType::PARAMETER, $paramAttrs[0]->target);
    }

    // --- Property-level attributes ---

    #[Test]
    public function scansPropertyLevelAttributes(): void
    {
        $map = $this->scanner->scan([AnnotatedService::class]);
        $classAttrs = $map->getClass(AnnotatedService::class);
        self::assertNotNull($classAttrs);

        $propAttrs = $classAttrs->propertyAttributes();
        self::assertCount(2, $propAttrs);

        $props = array_map(static fn($r) => $r->property, $propAttrs);
        self::assertContains('name', $props);
        self::assertContains('email', $props);

        foreach ($propAttrs as $attr) {
            self::assertSame(Column::class, $attr->attribute);
            self::assertSame(TargetType::PROPERTY, $attr->target);
        }
    }

    // --- Constant-level attributes ---

    #[Test]
    public function scansConstantLevelAttributes(): void
    {
        $map = $this->scanner->scan([ConstantFixture::class]);
        $classAttrs = $map->getClass(ConstantFixture::class);
        self::assertNotNull($classAttrs);

        $constantAttrs = $classAttrs->constantAttributes();
        self::assertCount(2, $constantAttrs);

        $constants = array_map(static fn($r) => $r->constant, $constantAttrs);
        self::assertContains('VERSION', $constants);
        self::assertContains('CONFIG_KEY', $constants);

        foreach ($constantAttrs as $attr) {
            self::assertSame(CacheKey::class, $attr->attribute);
            self::assertSame(TargetType::CONSTANT, $attr->target);
        }
    }

    #[Test]
    public function constantAttributeInstanceHasCorrectArguments(): void
    {
        $map = $this->scanner->scan([ConstantFixture::class]);
        $classAttrs = $map->getClass(ConstantFixture::class);
        self::assertNotNull($classAttrs);

        $version = $classAttrs->constantAttributes('VERSION');
        self::assertCount(1, $version);
        self::assertInstanceOf(CacheKey::class, $version[0]->instance);
        self::assertSame('v1', $version[0]->instance->prefix);
    }

    #[Test]
    public function constantWithoutAttributeIsNotScanned(): void
    {
        $map = $this->scanner->scan([ConstantFixture::class]);
        $classAttrs = $map->getClass(ConstantFixture::class);
        self::assertNotNull($classAttrs);

        $noAttr = $classAttrs->constantAttributes('NO_ATTR');
        self::assertCount(0, $noAttr);
    }

    // --- Structure type detection ---

    #[Test]
    public function detectsClassStructureType(): void
    {
        $map = $this->scanner->scan([AnnotatedController::class]);
        $classAttrs = $map->getClass(AnnotatedController::class);
        self::assertNotNull($classAttrs);
        self::assertSame(StructureType::CLASS_TYPE, $classAttrs->structureType);
    }

    #[Test]
    public function detectsInterfaceStructureType(): void
    {
        $map = $this->scanner->scan([AnnotatedInterface::class]);
        $classAttrs = $map->getClass(AnnotatedInterface::class);
        self::assertNotNull($classAttrs);
        self::assertSame(StructureType::INTERFACE_TYPE, $classAttrs->structureType);
    }

    #[Test]
    public function detectsEnumStructureType(): void
    {
        $map = $this->scanner->scan([AnnotatedEnum::class]);
        $classAttrs = $map->getClass(AnnotatedEnum::class);
        self::assertNotNull($classAttrs);
        self::assertSame(StructureType::ENUM_TYPE, $classAttrs->structureType);
    }

    #[Test]
    public function detectsTraitStructureType(): void
    {
        $map = $this->scanner->scan([AnnotatedTrait::class]);
        $classAttrs = $map->getClass(AnnotatedTrait::class);
        self::assertNotNull($classAttrs);
        self::assertSame(StructureType::TRAIT_TYPE, $classAttrs->structureType);
    }

    // --- Extends / Implements ---

    #[Test]
    public function detectsExtendsRelationship(): void
    {
        $map = $this->scanner->scan([ChildController::class]);
        $classAttrs = $map->getClass(ChildController::class);
        self::assertNotNull($classAttrs);
        self::assertSame(BaseController::class, $classAttrs->extends);
    }

    #[Test]
    public function detectsImplementsRelationship(): void
    {
        $map = $this->scanner->scan([ChildController::class]);
        $classAttrs = $map->getClass(ChildController::class);
        self::assertNotNull($classAttrs);
        self::assertContains(AnnotatedInterface::class, $classAttrs->implements);
    }

    #[Test]
    public function classWithNoParentHasNullExtends(): void
    {
        $map = $this->scanner->scan([AnnotatedController::class]);
        $classAttrs = $map->getClass(AnnotatedController::class);
        self::assertNotNull($classAttrs);
        self::assertNull($classAttrs->extends);
    }

    #[Test]
    public function classWithNoInterfacesHasEmptyImplements(): void
    {
        $map = $this->scanner->scan([AnnotatedController::class]);
        $classAttrs = $map->getClass(AnnotatedController::class);
        self::assertNotNull($classAttrs);
        self::assertSame([], $classAttrs->implements);
    }

    // --- Filter ---

    #[Test]
    public function filterReturnsOnlyMatchingAttributes(): void
    {
        $map = $this->scanner->scan([AnnotatedController::class], filter: [Route::class]);
        $classAttrs = $map->getClass(AnnotatedController::class);
        self::assertNotNull($classAttrs);

        foreach ($classAttrs->all() as $result) {
            self::assertSame(Route::class, $result->attribute);
        }
    }

    #[Test]
    public function filterWithMultipleAttributeClasses(): void
    {
        $map = $this->scanner->scan(
            [AnnotatedController::class],
            filter: [Route::class, Middleware::class],
        );
        $classAttrs = $map->getClass(AnnotatedController::class);
        self::assertNotNull($classAttrs);

        $allowed = [Route::class, Middleware::class];

        foreach ($classAttrs->all() as $result) {
            self::assertContains($result->attribute, $allowed);
        }

        $names = array_unique(array_map(static fn($r) => $r->attribute, $classAttrs->all()));
        self::assertCount(2, $names);
    }

    #[Test]
    public function filterExcludesNonMatchingFromAllTargets(): void
    {
        $map = $this->scanner->scan(
            [AnnotatedController::class, AnnotatedService::class],
            filter: [Column::class],
        );

        $controller = $map->getClass(AnnotatedController::class);
        self::assertNotNull($controller);
        self::assertCount(0, $controller->all());

        $service = $map->getClass(AnnotatedService::class);
        self::assertNotNull($service);

        foreach ($service->all() as $result) {
            self::assertSame(Column::class, $result->attribute);
        }
    }

    #[Test]
    public function emptyFilterReturnsAllAttributes(): void
    {
        $map = $this->scanner->scan([AnnotatedController::class], filter: []);
        $classAttrs = $map->getClass(AnnotatedController::class);
        self::assertNotNull($classAttrs);

        $names = array_unique(array_map(static fn($r) => $r->attribute, $classAttrs->all()));
        self::assertGreaterThan(1, count($names));
    }

    // --- Visibility filter ---

    #[Test]
    public function visibilityFilterPublicOnlyReturnPublicMethods(): void
    {
        $map = $this->scanner->scan(
            [VisibilityFixture::class],
            visibilityFilter: ReflectionMethod::IS_PUBLIC,
        );
        $classAttrs = $map->getClass(VisibilityFixture::class);
        self::assertNotNull($classAttrs);

        $methods = $classAttrs->methodAttributes();
        self::assertCount(1, $methods);
        self::assertSame('publicMethod', $methods[0]->method);
    }

    #[Test]
    public function visibilityFilterProtectedOnly(): void
    {
        $map = $this->scanner->scan(
            [VisibilityFixture::class],
            visibilityFilter: ReflectionMethod::IS_PROTECTED,
        );
        $classAttrs = $map->getClass(VisibilityFixture::class);
        self::assertNotNull($classAttrs);

        $methods = $classAttrs->methodAttributes();
        self::assertCount(1, $methods);
        self::assertSame('protectedMethod', $methods[0]->method);
    }

    #[Test]
    public function visibilityFilterPrivateOnly(): void
    {
        $map = $this->scanner->scan(
            [VisibilityFixture::class],
            visibilityFilter: ReflectionMethod::IS_PRIVATE,
        );
        $classAttrs = $map->getClass(VisibilityFixture::class);
        self::assertNotNull($classAttrs);

        $methods = $classAttrs->methodAttributes();
        self::assertCount(1, $methods);
        self::assertSame('privateMethod', $methods[0]->method);
    }

    #[Test]
    public function visibilityFilterZeroReturnsAllMethods(): void
    {
        $map = $this->scanner->scan(
            [VisibilityFixture::class],
            visibilityFilter: 0,
        );
        $classAttrs = $map->getClass(VisibilityFixture::class);
        self::assertNotNull($classAttrs);

        $methods = $classAttrs->methodAttributes();
        self::assertCount(3, $methods);
    }

    #[Test]
    public function visibilityFilterCombined(): void
    {
        $map = $this->scanner->scan(
            [VisibilityFixture::class],
            visibilityFilter: ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED,
        );
        $classAttrs = $map->getClass(VisibilityFixture::class);
        self::assertNotNull($classAttrs);

        $methods = $classAttrs->methodAttributes();
        self::assertCount(2, $methods);

        $names = array_map(static fn($r) => $r->method, $methods);
        self::assertContains('publicMethod', $names);
        self::assertContains('protectedMethod', $names);
    }

    // --- Repeatable attributes ---

    #[Test]
    public function repeatableAttributesMultipleRoutesOnController(): void
    {
        $map = $this->scanner->scan([AnnotatedController::class]);
        $classAttrs = $map->getClass(AnnotatedController::class);
        self::assertNotNull($classAttrs);

        $allRoutes = array_filter(
            $classAttrs->all(),
            static fn($r) => $r->attribute === Route::class,
        );

        // 1 class-level + 3 method-level = 4
        self::assertCount(4, $allRoutes);
    }

    // --- Plain class ---

    #[Test]
    public function plainClassIncludedInMapWithEmptyResults(): void
    {
        $map = $this->scanner->scan([PlainClass::class]);
        self::assertTrue($map->hasClass(PlainClass::class));

        $classAttrs = $map->getClass(PlainClass::class);
        self::assertNotNull($classAttrs);
        self::assertCount(0, $classAttrs->all());
    }

    // --- Multiple classes ---

    #[Test]
    public function multipleClassesScannedAtOnce(): void
    {
        $map = $this->scanner->scan([
            AnnotatedController::class,
            AnnotatedService::class,
            PlainClass::class,
        ]);

        self::assertSame(3, $map->count());
        self::assertTrue($map->hasClass(AnnotatedController::class));
        self::assertTrue($map->hasClass(AnnotatedService::class));
        self::assertTrue($map->hasClass(PlainClass::class));
    }

    // --- Broken attribute resilience ---

    #[Test]
    public function attributeThatThrowsIsSkipped(): void
    {
        $map = $this->scanner->scan([BrokenAttributeFixture::class]);
        $classAttrs = $map->getClass(BrokenAttributeFixture::class);
        self::assertNotNull($classAttrs);

        $brokenAttrs = array_filter(
            $classAttrs->all(),
            static fn($r) => $r->attribute === Broken::class,
        );
        self::assertCount(0, $brokenAttrs);

        $routeAttrs = array_filter(
            $classAttrs->all(),
            static fn($r) => $r->attribute === Route::class,
        );
        self::assertGreaterThan(0, count($routeAttrs));
    }

    #[Test]
    public function unloadableClassIsSkippedNotThrown(): void
    {
        /** @var class-string $missing */
        $missing = 'PHPdot\\Attribute\\Tests\\Fixtures\\NoSuchClass';

        $map = $this->scanner->scan([$missing, AnnotatedController::class]);

        self::assertNull($map->getClass($missing));
        self::assertNotNull($map->getClass(AnnotatedController::class));
    }

    // --- Inherited member filtering ---

    #[Test]
    public function inheritedMethodsAreNotScannedOnChild(): void
    {
        $map = $this->scanner->scan([ChildController::class]);
        $classAttrs = $map->getClass(ChildController::class);
        self::assertNotNull($classAttrs);

        $methods = array_map(static fn($r) => $r->method, $classAttrs->methodAttributes());
        self::assertContains('index', $methods);
    }

    // --- Empty scan ---

    #[Test]
    public function scanEmptyClassListReturnsEmptyMap(): void
    {
        $map = $this->scanner->scan([]);
        self::assertSame(0, $map->count());
    }

    // --- Arguments captured ---

    #[Test]
    public function argumentsAreCaptured(): void
    {
        $map = $this->scanner->scan([AnnotatedService::class]);
        $classAttrs = $map->getClass(AnnotatedService::class);
        self::assertNotNull($classAttrs);

        $injectable = $classAttrs->get(Injectable::class);
        self::assertNotNull($injectable);
        self::assertNotEmpty($injectable->arguments);
    }
}
