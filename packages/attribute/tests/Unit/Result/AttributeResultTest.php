<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Unit\Result;

use PHPdot\Attribute\Enum\TargetType;
use PHPdot\Attribute\Result\AttributeResult;
use PHPdot\Attribute\Tests\Fixtures\Attributes\Route;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AttributeResultTest extends TestCase
{
    #[Test]
    public function storesAllPropertiesCorrectly(): void
    {
        $instance = new Route('/users', ['GET'], 'users.index');
        $result = new AttributeResult(
            attribute: Route::class,
            instance: $instance,
            arguments: ['/users', ['GET'], 'users.index'],
            class: 'App\\Controller\\UserController',
            target: TargetType::METHOD,
            method: 'index',
            property: null,
            parameter: null,
            constant: null,
        );

        self::assertSame(Route::class, $result->attribute);
        self::assertSame($instance, $result->instance);
        self::assertSame(['/users', ['GET'], 'users.index'], $result->arguments);
        self::assertSame('App\\Controller\\UserController', $result->class);
        self::assertSame(TargetType::METHOD, $result->target);
        self::assertSame('index', $result->method);
        self::assertNull($result->property);
        self::assertNull($result->parameter);
        self::assertNull($result->constant);
    }

    #[Test]
    public function storesPropertyTarget(): void
    {
        $instance = new Route('/test');
        $result = new AttributeResult(
            attribute: Route::class,
            instance: $instance,
            arguments: ['/test'],
            class: 'App\\Model\\User',
            target: TargetType::PROPERTY,
            property: 'name',
        );

        self::assertSame(TargetType::PROPERTY, $result->target);
        self::assertSame('name', $result->property);
        self::assertNull($result->method);
    }

    #[Test]
    public function storesParameterTarget(): void
    {
        $instance = new Route('/test');
        $result = new AttributeResult(
            attribute: Route::class,
            instance: $instance,
            arguments: ['/test'],
            class: 'App\\Controller\\UserController',
            target: TargetType::PARAMETER,
            method: 'store',
            parameter: 'data',
        );

        self::assertSame(TargetType::PARAMETER, $result->target);
        self::assertSame('store', $result->method);
        self::assertSame('data', $result->parameter);
    }

    #[Test]
    public function propertiesAreReadonly(): void
    {
        $instance = new Route('/users');
        $result = new AttributeResult(
            attribute: Route::class,
            instance: $instance,
            arguments: ['/users'],
            class: 'App\\Controller\\UserController',
            target: TargetType::CLASS_TYPE,
        );

        $reflection = new \ReflectionClass($result);

        self::assertTrue($reflection->isReadOnly());
    }
}
