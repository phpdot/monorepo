<?php

declare(strict_types=1);

namespace PHPdot\ErrorHandler\Tests\Unit\Context;

use PHPdot\ErrorHandler\Context\ContextTab;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContextTabTest extends TestCase
{
    #[Test]
    public function storesLabel(): void
    {
        $tab = new ContextTab(label: 'Queries', data: []);

        self::assertSame('Queries', $tab->label);
    }

    #[Test]
    public function storesData(): void
    {
        $data = ['count' => 5, 'total_time' => '12ms'];
        $tab = new ContextTab(label: 'Queries', data: $data);

        self::assertSame($data, $tab->data);
    }

    #[Test]
    public function storesEmptyData(): void
    {
        $tab = new ContextTab(label: 'Empty', data: []);

        self::assertSame([], $tab->data);
    }

    #[Test]
    public function storesNestedData(): void
    {
        $data = ['queries' => [['sql' => 'SELECT 1', 'time' => '1ms']]];
        $tab = new ContextTab(label: 'DB', data: $data);

        self::assertSame($data, $tab->data);
    }

    #[Test]
    public function isReadonly(): void
    {
        $ref = new \ReflectionClass(ContextTab::class);

        self::assertTrue($ref->isReadOnly());
    }

    #[Test]
    public function isFinal(): void
    {
        $ref = new \ReflectionClass(ContextTab::class);

        self::assertTrue($ref->isFinal());
    }

    #[Test]
    public function storesLabelWithSpecialChars(): void
    {
        $tab = new ContextTab(label: 'Cache <Stats>', data: []);

        self::assertSame('Cache <Stats>', $tab->label);
    }

    #[Test]
    public function storesDataWithMixedValueTypes(): void
    {
        $data = ['string' => 'value', 'int' => 42, 'bool' => true, 'null' => null];
        $tab = new ContextTab(label: 'Mixed', data: $data);

        self::assertSame($data, $tab->data);
    }
}
