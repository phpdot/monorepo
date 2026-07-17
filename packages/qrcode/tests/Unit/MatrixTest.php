<?php

declare(strict_types=1);

namespace PHPdot\QrCode\Tests\Unit;

use OutOfRangeException;
use PHPdot\QrCode\Matrix;
use PHPUnit\Framework\TestCase;

final class MatrixTest extends TestCase
{
    public function test_reports_dark_and_light_modules(): void
    {
        $matrix = new Matrix([
            [true, false],
            [false, true],
        ], 2);

        self::assertSame(2, $matrix->size);
        self::assertTrue($matrix->isDark(0, 0));
        self::assertFalse($matrix->isDark(1, 0));
        self::assertFalse($matrix->isDark(0, 1));
        self::assertTrue($matrix->isDark(1, 1));
    }

    public function test_out_of_range_coordinate_throws(): void
    {
        $matrix = new Matrix([[true]], 1);

        $this->expectException(OutOfRangeException::class);

        $matrix->isDark(1, 0);
    }

    public function test_negative_coordinate_throws(): void
    {
        $matrix = new Matrix([[true]], 1);

        $this->expectException(OutOfRangeException::class);

        $matrix->isDark(0, -1);
    }

    public function test_to_array_returns_grid(): void
    {
        $grid = [[true, false], [false, true]];

        self::assertSame($grid, (new Matrix($grid, 2))->toArray());
    }
}
