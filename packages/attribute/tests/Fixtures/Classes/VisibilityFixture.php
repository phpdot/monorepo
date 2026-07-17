<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Fixtures\Classes;

use PHPdot\Attribute\Tests\Fixtures\Attributes\Route;

final class VisibilityFixture
{
    #[Route('/public')]
    public function publicMethod(): void {}

    #[Route('/protected')]
    protected function protectedMethod(): void {}

    #[Route('/private')]
    private function privateMethod(): void {}
}
