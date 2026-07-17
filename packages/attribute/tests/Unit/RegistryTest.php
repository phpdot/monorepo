<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Unit;

use PHPdot\Attribute\Enum\StructureType;
use PHPdot\Attribute\Enum\TargetType;
use PHPdot\Attribute\Registry;
use PHPdot\Attribute\Result\AttributeMap;
use PHPdot\Attribute\Result\AttributeResult;
use PHPdot\Attribute\Result\ClassAttributes;
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
use PHPdot\Attribute\Tests\Fixtures\Classes\ChildController;
use PHPdot\Attribute\Tests\Fixtures\Classes\PlainClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase
{
    private Registry $registry;

    protected function setUp(): void
    {
        $map = $this->buildFullMap();
        $this->registry = new Registry($map);
    }

    // --- findByAttribute ---

    #[Test]
    public function findByAttributeReturnsAllMatching(): void
    {
        $results = $this->registry->findByAttribute(Route::class);
        self::assertNotEmpty($results);

        foreach ($results as $result) {
            self::assertSame(Route::class, $result->attribute);
        }
    }

    #[Test]
    public function findByAttributeSpansMultipleClasses(): void
    {
        $results = $this->registry->findByAttribute(Route::class);
        $classes = array_unique(array_map(static fn(AttributeResult $r): string => $r->class, $results));
        self::assertGreaterThan(1, count($classes));
    }

    #[Test]
    public function findByAttributeReturnsEmptyForNonExistent(): void
    {
        self::assertEmpty($this->registry->findByAttribute(Validated::class));
    }

    // --- hasAttribute ---

    #[Test]
    public function hasAttributeReturnsTrueWhenExists(): void
    {
        self::assertTrue($this->registry->hasAttribute(Route::class));
        self::assertTrue($this->registry->hasAttribute(Injectable::class));
        self::assertTrue($this->registry->hasAttribute(Column::class));
        self::assertTrue($this->registry->hasAttribute(Middleware::class));
        self::assertTrue($this->registry->hasAttribute(CacheKey::class));
    }

    #[Test]
    public function hasAttributeReturnsFalseWhenNotExists(): void
    {
        self::assertFalse($this->registry->hasAttribute(Validated::class));
    }

    // --- findByClass ---

    #[Test]
    public function findByClassReturnsClassAttributes(): void
    {
        $result = $this->registry->findByClass(AnnotatedController::class);
        self::assertNotNull($result);
        self::assertSame(AnnotatedController::class, $result->class);
    }

    #[Test]
    public function findByClassReturnsNullForUnknown(): void
    {
        self::assertNull($this->registry->findByClass('App\\NonExistent'));
    }

    #[Test]
    public function findByClassWithIncludeParentsMergesParentAttributes(): void
    {
        $result = $this->registry->findByClass(ChildController::class, includeParents: true);
        self::assertNotNull($result);
        self::assertSame(ChildController::class, $result->class);

        // ChildController has 2 own results + parent 'App\SomeAbstractParent' has 1
        self::assertCount(3, $result->all());
    }

    #[Test]
    public function findByClassWithIncludeParentsFalseReturnsOnlyOwnAttributes(): void
    {
        $result = $this->registry->findByClass(ChildController::class, includeParents: false);
        self::assertNotNull($result);
        self::assertCount(2, $result->all());
    }

    #[Test]
    public function findByClassWithIncludeParentsStopsWhenParentNotInMap(): void
    {
        $result = $this->registry->findByClass(AnnotatedController::class, includeParents: true);
        self::assertNotNull($result);
        // No parent in map, same count as without includeParents
        self::assertSame(AnnotatedController::class, $result->class);
    }

    #[Test]
    public function findByClassReturnsNullForUnknownEvenWithIncludeParents(): void
    {
        self::assertNull($this->registry->findByClass('App\\NonExistent', includeParents: true));
    }

    // --- getClassesWithAttribute ---

    #[Test]
    public function getClassesWithAttributeReturnsClassNames(): void
    {
        $classes = $this->registry->getClassesWithAttribute(Route::class);
        self::assertContains(AnnotatedController::class, $classes);
        self::assertContains(ChildController::class, $classes);
    }

    #[Test]
    public function getClassesWithAttributeExcludesUnmatched(): void
    {
        $classes = $this->registry->getClassesWithAttribute(Column::class);
        self::assertContains(AnnotatedService::class, $classes);
        self::assertNotContains(AnnotatedController::class, $classes);
    }

    // --- findClassAttributes ---

    #[Test]
    public function findClassAttributesReturnsClassLevelOnly(): void
    {
        $results = $this->registry->findClassAttributes(Route::class);
        self::assertNotEmpty($results);

        foreach ($results as $result) {
            self::assertSame(TargetType::CLASS_TYPE, $result->target);
            self::assertSame(Route::class, $result->attribute);
        }
    }

    // --- findMethodAttributes ---

    #[Test]
    public function findMethodAttributesReturnsMethodLevelOnly(): void
    {
        $results = $this->registry->findMethodAttributes(Route::class);
        self::assertNotEmpty($results);

        foreach ($results as $result) {
            self::assertSame(TargetType::METHOD, $result->target);
            self::assertSame(Route::class, $result->attribute);
        }
    }

    // --- findPropertyAttributes ---

    #[Test]
    public function findPropertyAttributesReturnsPropertyLevelOnly(): void
    {
        $results = $this->registry->findPropertyAttributes(Column::class);
        self::assertNotEmpty($results);

        foreach ($results as $result) {
            self::assertSame(TargetType::PROPERTY, $result->target);
            self::assertSame(Column::class, $result->attribute);
        }
    }

    // --- findParameterAttributes ---

    #[Test]
    public function findParameterAttributesReturnsParameterLevelOnly(): void
    {
        $map = $this->buildMapWithParameters();
        $registry = new Registry($map);

        $results = $registry->findParameterAttributes(Validated::class);
        self::assertNotEmpty($results);

        foreach ($results as $result) {
            self::assertSame(TargetType::PARAMETER, $result->target);
        }
    }

    // --- findByMethod ---

    #[Test]
    public function findByMethodReturnsAttributesForSpecificMethod(): void
    {
        $results = $this->registry->findByMethod(AnnotatedController::class, 'index');
        self::assertNotEmpty($results);

        foreach ($results as $result) {
            self::assertSame('index', $result->method);
        }
    }

    #[Test]
    public function findByMethodReturnsEmptyForUnknownClass(): void
    {
        self::assertEmpty($this->registry->findByMethod('App\\NonExistent', 'index'));
    }

    #[Test]
    public function findByMethodReturnsEmptyForUnknownMethod(): void
    {
        self::assertEmpty($this->registry->findByMethod(AnnotatedController::class, 'nonExistent'));
    }

    // --- findImplementing ---

    #[Test]
    public function findImplementingReturnsClassNames(): void
    {
        $classes = $this->registry->findImplementing(AnnotatedInterface::class);
        self::assertContains(ChildController::class, $classes);
    }

    #[Test]
    public function findImplementingReturnsEmptyForNoMatch(): void
    {
        self::assertEmpty($this->registry->findImplementing('App\\NonExistent'));
    }

    // --- findExtending ---

    #[Test]
    public function findExtendingReturnsClassNames(): void
    {
        $classes = $this->registry->findExtending('App\\SomeAbstractParent');
        self::assertContains(ChildController::class, $classes);
    }

    #[Test]
    public function findExtendingReturnsEmptyForNoMatch(): void
    {
        self::assertEmpty($this->registry->findExtending('App\\NonExistent'));
    }

    // --- findEnums ---

    #[Test]
    public function findEnumsReturnsEnumClassNames(): void
    {
        $enums = $this->registry->findEnums();
        self::assertContains(AnnotatedEnum::class, $enums);
    }

    #[Test]
    public function findEnumsExcludesNonEnums(): void
    {
        $enums = $this->registry->findEnums();
        self::assertNotContains(AnnotatedController::class, $enums);
    }

    // --- findInterfaces ---

    #[Test]
    public function findInterfacesReturnsInterfaceClassNames(): void
    {
        $interfaces = $this->registry->findInterfaces();
        self::assertContains(AnnotatedInterface::class, $interfaces);
    }

    #[Test]
    public function findInterfacesExcludesNonInterfaces(): void
    {
        $interfaces = $this->registry->findInterfaces();
        self::assertNotContains(AnnotatedController::class, $interfaces);
    }

    // --- Traits ---

    #[Test]
    public function traitIsNotReturnedByFindEnumsOrInterfaces(): void
    {
        self::assertNotContains(AnnotatedTrait::class, $this->registry->findEnums());
        self::assertNotContains(AnnotatedTrait::class, $this->registry->findInterfaces());
    }

    // --- count ---

    #[Test]
    public function countReturnsTotalClassCount(): void
    {
        self::assertGreaterThan(0, $this->registry->count());
    }

    #[Test]
    public function countByAttributeReturnsAttributeCount(): void
    {
        $count = $this->registry->countByAttribute(Route::class);
        self::assertGreaterThan(0, $count);
        self::assertSame(count($this->registry->findByAttribute(Route::class)), $count);
    }

    #[Test]
    public function countByAttributeReturnsZeroForNonExistent(): void
    {
        self::assertSame(0, $this->registry->countByAttribute(Validated::class));
    }

    // --- all ---

    #[Test]
    public function allReturnsAllResults(): void
    {
        $all = $this->registry->all();
        self::assertNotEmpty($all);

        $classes = array_unique(array_map(static fn(AttributeResult $r): string => $r->class, $all));
        self::assertGreaterThanOrEqual(3, count($classes));
    }

    // --- getMap ---

    #[Test]
    public function getMapReturnsOriginalMap(): void
    {
        $map = $this->registry->getMap();
        self::assertInstanceOf(AttributeMap::class, $map);
    }

    // --- findClasses ---

    #[Test]
    public function findClassesReturnsClassStructureTypes(): void
    {
        $classes = $this->registry->findClasses();
        self::assertContains(AnnotatedController::class, $classes);
        self::assertNotContains(AnnotatedEnum::class, $classes);
        self::assertNotContains(AnnotatedInterface::class, $classes);
        self::assertNotContains(AnnotatedTrait::class, $classes);
    }

    // --- findTraits ---

    #[Test]
    public function findTraitsReturnsTraitStructureTypes(): void
    {
        $traits = $this->registry->findTraits();
        self::assertContains(AnnotatedTrait::class, $traits);
        self::assertNotContains(AnnotatedController::class, $traits);
    }

    // --- findConstantAttributes ---

    #[Test]
    public function findConstantAttributesReturnsConstantLevelOnly(): void
    {
        $results = $this->registry->findConstantAttributes(CacheKey::class);
        self::assertNotEmpty($results);

        foreach ($results as $result) {
            self::assertSame(TargetType::CONSTANT, $result->target);
            self::assertSame(CacheKey::class, $result->attribute);
        }
    }

    #[Test]
    public function findConstantAttributesWithoutFilterReturnsAll(): void
    {
        $results = $this->registry->findConstantAttributes();
        self::assertNotEmpty($results);

        foreach ($results as $result) {
            self::assertSame(TargetType::CONSTANT, $result->target);
        }
    }

    // --- Target finders with null attribute class ---

    #[Test]
    public function findMethodAttributesWithoutFilterReturnsAll(): void
    {
        $results = $this->registry->findMethodAttributes();
        self::assertNotEmpty($results);

        foreach ($results as $result) {
            self::assertSame(TargetType::METHOD, $result->target);
        }
    }

    #[Test]
    public function findClassAttributesWithoutFilterReturnsAll(): void
    {
        $results = $this->registry->findClassAttributes();
        self::assertNotEmpty($results);

        foreach ($results as $result) {
            self::assertSame(TargetType::CLASS_TYPE, $result->target);
        }
    }

    #[Test]
    public function findPropertyAttributesWithoutFilterReturnsAll(): void
    {
        $results = $this->registry->findPropertyAttributes();
        self::assertNotEmpty($results);

        foreach ($results as $result) {
            self::assertSame(TargetType::PROPERTY, $result->target);
        }
    }

    // --- Empty registry ---

    #[Test]
    public function emptyRegistryReturnsEmptyResults(): void
    {
        $emptyMap = new AttributeMap(classes: [], generatedAt: time(), directories: [], filter: []);
        $registry = new Registry($emptyMap);

        self::assertEmpty($registry->findByAttribute(Route::class));
        self::assertFalse($registry->hasAttribute(Route::class));
        self::assertNull($registry->findByClass('App\\X'));
        self::assertEmpty($registry->getClassesWithAttribute(Route::class));
        self::assertEmpty($registry->findClassAttributes(Route::class));
        self::assertEmpty($registry->findMethodAttributes(Route::class));
        self::assertEmpty($registry->findPropertyAttributes(Column::class));
        self::assertEmpty($registry->findParameterAttributes(Validated::class));
        self::assertEmpty($registry->findByMethod('App\\X', 'y'));
        self::assertEmpty($registry->findClasses());
        self::assertEmpty($registry->findTraits());
        self::assertEmpty($registry->findEnums());
        self::assertEmpty($registry->findInterfaces());
        self::assertEmpty($registry->findConstantAttributes());
        self::assertEmpty($registry->findExtending('App\\X'));
        self::assertEmpty($registry->findImplementing('App\\X'));
        self::assertSame(0, $registry->count());
        self::assertSame(0, $registry->countByAttribute(Route::class));
        self::assertEmpty($registry->all());
    }

    private function buildFullMap(): AttributeMap
    {
        $controllerResults = [
            new AttributeResult(
                attribute: Route::class,
                instance: new Route('/users'),
                arguments: ['/users'],
                class: AnnotatedController::class,
                target: TargetType::CLASS_TYPE,
            ),
            new AttributeResult(
                attribute: Middleware::class,
                instance: new Middleware('auth'),
                arguments: ['auth'],
                class: AnnotatedController::class,
                target: TargetType::CLASS_TYPE,
            ),
            new AttributeResult(
                attribute: Route::class,
                instance: new Route('/users', ['GET'], 'users.index'),
                arguments: ['/users', ['GET'], 'users.index'],
                class: AnnotatedController::class,
                target: TargetType::METHOD,
                method: 'index',
            ),
            new AttributeResult(
                attribute: Route::class,
                instance: new Route('/users/{id}', ['GET'], 'users.show'),
                arguments: ['/users/{id}', ['GET'], 'users.show'],
                class: AnnotatedController::class,
                target: TargetType::METHOD,
                method: 'show',
            ),
        ];

        $serviceResults = [
            new AttributeResult(
                attribute: Injectable::class,
                instance: new Injectable(singleton: true),
                arguments: [true],
                class: AnnotatedService::class,
                target: TargetType::CLASS_TYPE,
            ),
            new AttributeResult(
                attribute: Column::class,
                instance: new Column('user_name'),
                arguments: ['user_name'],
                class: AnnotatedService::class,
                target: TargetType::PROPERTY,
                property: 'name',
            ),
            new AttributeResult(
                attribute: Column::class,
                instance: new Column('user_email'),
                arguments: ['user_email'],
                class: AnnotatedService::class,
                target: TargetType::PROPERTY,
                property: 'email',
            ),
        ];

        $childResults = [
            new AttributeResult(
                attribute: Route::class,
                instance: new Route('/admin/users'),
                arguments: ['/admin/users'],
                class: ChildController::class,
                target: TargetType::CLASS_TYPE,
            ),
            new AttributeResult(
                attribute: Route::class,
                instance: new Route('/admin/users', ['GET']),
                arguments: ['/admin/users', ['GET']],
                class: ChildController::class,
                target: TargetType::METHOD,
                method: 'index',
            ),
        ];

        $constantResults = [
            new AttributeResult(
                attribute: CacheKey::class,
                instance: new CacheKey(prefix: 'v1'),
                arguments: ['v1'],
                class: 'App\\Config',
                target: TargetType::CONSTANT,
                constant: 'VERSION',
            ),
        ];

        return new AttributeMap(
            classes: [
                AnnotatedController::class => new ClassAttributes(
                    class: AnnotatedController::class,
                    structureType: StructureType::CLASS_TYPE,
                    implements: [],
                    extends: null,
                    results: $controllerResults,
                ),
                AnnotatedService::class => new ClassAttributes(
                    class: AnnotatedService::class,
                    structureType: StructureType::CLASS_TYPE,
                    implements: [],
                    extends: null,
                    results: $serviceResults,
                ),
                PlainClass::class => new ClassAttributes(
                    class: PlainClass::class,
                    structureType: StructureType::CLASS_TYPE,
                    implements: [],
                    extends: null,
                    results: [],
                ),
                ChildController::class => new ClassAttributes(
                    class: ChildController::class,
                    structureType: StructureType::CLASS_TYPE,
                    implements: [AnnotatedInterface::class],
                    extends: 'App\\SomeAbstractParent',
                    results: $childResults,
                ),
                AnnotatedEnum::class => new ClassAttributes(
                    class: AnnotatedEnum::class,
                    structureType: StructureType::ENUM_TYPE,
                    implements: [],
                    extends: null,
                    results: [new AttributeResult(
                        attribute: Injectable::class,
                        instance: new Injectable(),
                        arguments: [],
                        class: AnnotatedEnum::class,
                        target: TargetType::CLASS_TYPE,
                    )],
                ),
                AnnotatedInterface::class => new ClassAttributes(
                    class: AnnotatedInterface::class,
                    structureType: StructureType::INTERFACE_TYPE,
                    implements: [],
                    extends: null,
                    results: [new AttributeResult(
                        attribute: Injectable::class,
                        instance: new Injectable(),
                        arguments: [],
                        class: AnnotatedInterface::class,
                        target: TargetType::CLASS_TYPE,
                    )],
                ),
                AnnotatedTrait::class => new ClassAttributes(
                    class: AnnotatedTrait::class,
                    structureType: StructureType::TRAIT_TYPE,
                    implements: [],
                    extends: null,
                    results: [new AttributeResult(
                        attribute: Injectable::class,
                        instance: new Injectable(),
                        arguments: [],
                        class: AnnotatedTrait::class,
                        target: TargetType::CLASS_TYPE,
                    )],
                ),
                'App\\SomeAbstractParent' => new ClassAttributes(
                    class: 'App\\SomeAbstractParent',
                    structureType: StructureType::CLASS_TYPE,
                    implements: [],
                    extends: null,
                    results: [new AttributeResult(
                        attribute: Middleware::class,
                        instance: new Middleware('admin'),
                        arguments: ['admin'],
                        class: 'App\\SomeAbstractParent',
                        target: TargetType::CLASS_TYPE,
                    )],
                ),
                'App\\Config' => new ClassAttributes(
                    class: 'App\\Config',
                    structureType: StructureType::CLASS_TYPE,
                    implements: [],
                    extends: null,
                    results: $constantResults,
                ),
            ],
            generatedAt: time(),
            directories: [],
            filter: [],
        );
    }

    private function buildMapWithParameters(): AttributeMap
    {
        return new AttributeMap(
            classes: [
                AnnotatedController::class => new ClassAttributes(
                    class: AnnotatedController::class,
                    structureType: StructureType::CLASS_TYPE,
                    implements: [],
                    extends: null,
                    results: [
                        new AttributeResult(
                            attribute: Validated::class,
                            instance: new Validated(),
                            arguments: [],
                            class: AnnotatedController::class,
                            target: TargetType::PARAMETER,
                            method: 'store',
                            parameter: 'data',
                        ),
                    ],
                ),
            ],
            generatedAt: time(),
            directories: [],
            filter: [],
        );
    }
}
