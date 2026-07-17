<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Unit\Result;

use PHPdot\Attribute\Enum\StructureType;
use PHPdot\Attribute\Enum\TargetType;
use PHPdot\Attribute\Result\AttributeResult;
use PHPdot\Attribute\Result\ClassAttributes;
use PHPdot\Attribute\Tests\Fixtures\Attributes\Column;
use PHPdot\Attribute\Tests\Fixtures\Attributes\Injectable;
use PHPdot\Attribute\Tests\Fixtures\Attributes\Middleware;
use PHPdot\Attribute\Tests\Fixtures\Attributes\Route;
use PHPdot\Attribute\Tests\Fixtures\Attributes\Validated;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClassAttributesTest extends TestCase
{
    #[Test]
    public function allReturnsAllResults(): void
    {
        $results = [
            $this->createResult(Route::class, TargetType::CLASS_TYPE),
            $this->createResult(Route::class, TargetType::METHOD, method: 'index'),
        ];
        $ca = $this->createClassAttributes($results);

        self::assertCount(2, $ca->all());
    }

    #[Test]
    public function classAttributesReturnsOnlyClassLevel(): void
    {
        $results = [
            $this->createResult(Route::class, TargetType::CLASS_TYPE),
            $this->createResult(Middleware::class, TargetType::CLASS_TYPE),
            $this->createResult(Route::class, TargetType::METHOD, method: 'index'),
        ];
        $ca = $this->createClassAttributes($results);

        $classLevel = $ca->classAttributes();

        self::assertCount(2, $classLevel);
        self::assertSame(TargetType::CLASS_TYPE, $classLevel[0]->target);
        self::assertSame(TargetType::CLASS_TYPE, $classLevel[1]->target);
    }

    #[Test]
    public function methodAttributesReturnsOnlyMethodLevel(): void
    {
        $results = [
            $this->createResult(Route::class, TargetType::CLASS_TYPE),
            $this->createResult(Route::class, TargetType::METHOD, method: 'index'),
            $this->createResult(Route::class, TargetType::METHOD, method: 'show'),
        ];
        $ca = $this->createClassAttributes($results);

        $methodLevel = $ca->methodAttributes();

        self::assertCount(2, $methodLevel);
        self::assertSame('index', $methodLevel[0]->method);
        self::assertSame('show', $methodLevel[1]->method);
    }

    #[Test]
    public function methodAttributesFiltersByMethodName(): void
    {
        $results = [
            $this->createResult(Route::class, TargetType::METHOD, method: 'index'),
            $this->createResult(Route::class, TargetType::METHOD, method: 'show'),
        ];
        $ca = $this->createClassAttributes($results);

        $indexOnly = $ca->methodAttributes('index');

        self::assertCount(1, $indexOnly);
        self::assertSame('index', $indexOnly[0]->method);
    }

    #[Test]
    public function propertyAttributesReturnsOnlyPropertyLevel(): void
    {
        $results = [
            $this->createResult(Injectable::class, TargetType::CLASS_TYPE),
            $this->createResult(Column::class, TargetType::PROPERTY, property: 'name'),
            $this->createResult(Column::class, TargetType::PROPERTY, property: 'email'),
        ];
        $ca = $this->createClassAttributes($results);

        $propLevel = $ca->propertyAttributes();

        self::assertCount(2, $propLevel);
        self::assertSame('name', $propLevel[0]->property);
        self::assertSame('email', $propLevel[1]->property);
    }

    #[Test]
    public function propertyAttributesFiltersByPropertyName(): void
    {
        $results = [
            $this->createResult(Column::class, TargetType::PROPERTY, property: 'name'),
            $this->createResult(Column::class, TargetType::PROPERTY, property: 'email'),
        ];
        $ca = $this->createClassAttributes($results);

        $nameOnly = $ca->propertyAttributes('name');

        self::assertCount(1, $nameOnly);
        self::assertSame('name', $nameOnly[0]->property);
    }

    #[Test]
    public function parameterAttributesReturnsOnlyParameterLevel(): void
    {
        $results = [
            $this->createResult(Route::class, TargetType::METHOD, method: 'store'),
            $this->createResult(Validated::class, TargetType::PARAMETER, method: 'store', parameter: 'data'),
        ];
        $ca = $this->createClassAttributes($results);

        $paramLevel = $ca->parameterAttributes();

        self::assertCount(1, $paramLevel);
        self::assertSame('data', $paramLevel[0]->parameter);
    }

    #[Test]
    public function parameterAttributesFiltersByMethodAndParameter(): void
    {
        $results = [
            $this->createResult(Validated::class, TargetType::PARAMETER, method: 'store', parameter: 'data'),
            $this->createResult(Validated::class, TargetType::PARAMETER, method: 'update', parameter: 'data'),
        ];
        $ca = $this->createClassAttributes($results);

        $storeOnly = $ca->parameterAttributes('store');
        self::assertCount(1, $storeOnly);
        self::assertSame('store', $storeOnly[0]->method);

        $specific = $ca->parameterAttributes('store', 'data');
        self::assertCount(1, $specific);
        self::assertSame('data', $specific[0]->parameter);
    }

    #[Test]
    public function hasReturnsTrueWhenAttributeExists(): void
    {
        $results = [
            $this->createResult(Route::class, TargetType::CLASS_TYPE),
        ];
        $ca = $this->createClassAttributes($results);

        self::assertTrue($ca->has(Route::class));
    }

    #[Test]
    public function hasReturnsFalseWhenAttributeDoesNotExist(): void
    {
        $results = [
            $this->createResult(Route::class, TargetType::CLASS_TYPE),
        ];
        $ca = $this->createClassAttributes($results);

        self::assertFalse($ca->has(Injectable::class));
    }

    #[Test]
    public function getReturnsFirstMatchingResult(): void
    {
        $results = [
            $this->createResult(Route::class, TargetType::CLASS_TYPE),
            $this->createResult(Middleware::class, TargetType::CLASS_TYPE),
        ];
        $ca = $this->createClassAttributes($results);

        $result = $ca->get(Middleware::class);

        self::assertNotNull($result);
        self::assertSame(Middleware::class, $result->attribute);
    }

    #[Test]
    public function getReturnsNullWhenNotFound(): void
    {
        $ca = $this->createClassAttributes([]);

        self::assertNull($ca->get(Route::class));
    }

    #[Test]
    public function constantAttributesReturnsOnlyConstantLevel(): void
    {
        $results = [
            $this->createResult(Route::class, TargetType::CLASS_TYPE),
            $this->createResult(Route::class, TargetType::CONSTANT, constant: 'FOO'),
        ];
        $ca = $this->createClassAttributes($results);

        $constants = $ca->constantAttributes();

        self::assertCount(1, $constants);
        self::assertSame('FOO', $constants[0]->constant);
    }

    #[Test]
    public function constantAttributesFiltersByConstantName(): void
    {
        $results = [
            $this->createResult(Route::class, TargetType::CONSTANT, constant: 'FOO'),
            $this->createResult(Route::class, TargetType::CONSTANT, constant: 'BAR'),
        ];
        $ca = $this->createClassAttributes($results);

        $fooOnly = $ca->constantAttributes('FOO');

        self::assertCount(1, $fooOnly);
        self::assertSame('FOO', $fooOnly[0]->constant);
    }

    /**
     * @param list<AttributeResult> $results
     */
    private function createClassAttributes(array $results): ClassAttributes
    {
        return new ClassAttributes(
            class: 'App\\Test\\Example',
            structureType: StructureType::CLASS_TYPE,
            implements: [],
            extends: null,
            results: $results,
        );
    }

    private function createResult(
        string $attribute,
        TargetType $target,
        ?string $method = null,
        ?string $property = null,
        ?string $parameter = null,
        ?string $constant = null,
    ): AttributeResult {
        return new AttributeResult(
            attribute: $attribute,
            instance: new $attribute(...$this->getDefaultArgs($attribute)),
            arguments: [],
            class: 'App\\Test\\Example',
            target: $target,
            method: $method,
            property: $property,
            parameter: $parameter,
            constant: $constant,
        );
    }

    /**
     * @return list<mixed>
     */
    private function getDefaultArgs(string $attribute): array
    {
        return match ($attribute) {
            Route::class => ['/test'],
            Middleware::class => ['test'],
            Injectable::class => [],
            Column::class => ['test'],
            Validated::class => [],
            default => [],
        };
    }
}
